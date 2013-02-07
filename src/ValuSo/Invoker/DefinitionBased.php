<?php
namespace ValuSo\Invoker;

use Valu\Service\ServiceEvent;
use Valu\Service\ServiceInterface;
use Valu\Service\Exception;
use Valu\Service\Definition;
use Valu\Service\Invoker\InvokerInterface;
use Valu\Service\Feature\DefinitionProviderInterface;
use Zend\Cache\Storage\StorageInterface;
use Zend\Stdlib\ArrayUtils;

class DefinitionBased implements InvokerInterface
{

    const CACHE_NS = 'valu_service_definition_';
    
    /**
     * Cache adapter
     *
     * @var Zend\Cache\Storage\StorageInterface
     */
    private $cache;

    public function __construct(StorageInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Invoke a method
     *
     * @param $service ServiceInterface           
     * @param $e ServiceEvent           
     * @throws \InvalidArgumentException
     * @throws Exception\OperationNotFoundException
     * @throws Exception\UnsupportedContextException
     * @return mixed
     */
    public function invoke(ServiceInterface $service, ServiceEvent $e)
    {
        
        if (! $service instanceof DefinitionProviderInterface) {
            throw new \InvalidArgumentException(
                    'Service must implement DefinitionProviderInterface');
        }
        
        $definition = $this->defineService($service);
        $operation = $e->getOperation();
        
        if (! $definition->hasOperation($operation)) {
            throw new Exception\OperationNotFoundException(
                    'Service ' . get_class($service) .
                             " doesn't provide operation " . $operation);
        }
        
        $operationDef = $definition->defineOperation($operation);
        
        /**
         * Test that current context is within supported contexts
         */
        if (isset($operationDef['meta']['contexts'])) {
            $contexts = preg_split('# +#', $operationDef['meta']['contexts']);
            
            if (! in_array($e->getContext(), $contexts)) {
                throw new Exception\UnsupportedContextException(
                    sprintf(
                        "Operation %s is not supported in %s service context",
                        $operation, $e->getContext()));
            }
        }
        
        $params = $this->resolveParams($definition, $operation, $e->getParams());
        
        switch (count($params)) {
            case 0:
                $response = $service->{$operation}();
                break;
            case 1:
                $response = $service->{$operation}($params[0]);
                break;
            case 2:
                $response = $service->{$operation}($params[0], $params[1]);
                break;
            case 3:
                $response = $service->{$operation}($params[0], $params[1], 
                        $params[2]);
                break;
            case 4:
                $response = $service->{$operation}($params[0], $params[1], 
                        $params[2], $params[3]);
                break;
            case 5:
                $response = $service->{$operation}($params[0], $params[1], 
                        $params[2], $params[3], $params[4]);
                break;
            default:
                $response = call_user_func_array(array($this, $operation), 
                        $params);
                
                break;
        }
        
        return $response;
    }

    /**
     * Fetch service definition
     *
     * @param $service DefinitionProviderInterface           
     * @return array
     */
    private function defineService(DefinitionProviderInterface $service)
    {
        $class = get_class($service);
        $cacheId = self::CACHE_NS . str_replace('\\', '_', $class);
        $version = $class::version();
        $definition = null;
        
        /**
         * Fetch from cache
         */
        if ($this->cache && $this->cache->hasItem($cacheId)) {
            $definition = $this->cache->getItem($cacheId);
            
            if ($definition && $definition->getVersion() !== $version) {
                $definition = null;
            } elseif(!$definition) {
                $definition = null;
            }
        }
        
        if (is_null($definition)) {
            $definition = $service->define();
            $definition->setVersion($version);
            
            /**
             * Cache definition
             */
            if ($this->cache) {
                $this->cache->setItem($cacheId, $definition);
            }
        }
        
        return $definition;
    }

    /**
     * Resolve parameter order for operation based on given arguments
     *
     * @param $definition DriverInterface           
     */
    private function resolveParams(Definition $definition, $operation, $args)
    {

        // We don't want to manipulate the params object
        if ($args instanceof \ArrayObject) {
            $args = $args->getArrayCopy();
        }
        
        // Return numeric parameter list as is and let
        // PHP handle errors
        if (array_key_exists(0, $args)) {
            return $args;
        }
        
        $definition = $definition->defineOperation($operation);
        $resolved = array();
        
        foreach ($definition['params'] as $name => $specs) {
            if (array_key_exists($name, $args)) {
                $resolved[] = $args[$name];
                unset($args[$name]);
            } else 
                if ($specs['has_default_value']) {
                    $resolved[] = $specs['default_value'];
                } else 
                    if (! $specs['optional']) {
                        throw new Exception\MissingParameterException(
                                'Parameter ' . $name . ' is missing');
                    }
        }
        
        if (sizeof($args)) {
            
            if ($args instanceof \ArrayAccess) {
                foreach ($args as $key => $value) {
                    $param = $key;
                    break;
                }
            } else {
                $params = array_keys($args);
                $param = array_shift($params);
            }
            
            throw new Exception\UnknownParameterException(
                    'Unknown parameter ' . $param);
        }
        
        return $resolved;
    }
}
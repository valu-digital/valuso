<?php
namespace ValuSo\Proxy;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;

class ServiceProxyGenerator extends ProxyGenerator
{
    const DEFAULT_SERVICE_PROXY_NS = 'ValuSo\\Proxy';
    
    const EVENT_PRE = 'pre';
    
    const EVENT_POST = 'post';

    /**
     * Proxy namespace
     * 
     * @var string
     */
    protected $proxyNamespace;
    
    /**
     * Service configurations
     * 
     * @var ArrayObject
     */
    private $serviceConfig;
    
    /**
     * @see Doctrine\Common\Proxy\ProxyGenerator::__construct()
     */
    public function __construct($proxyDir = null, $proxyNs = null)
    {
        $proxyDir = $proxyDir ?: sys_get_temp_dir();
        $proxyNs  = $proxyNs  ?: static::DEFAULT_SERVICE_PROXY_NS;

        $this->proxyNamespace = $proxyNs;
        
        parent::__construct($proxyDir, $proxyNs);
        
        $this->setPlaceholder('constructorImpl', array($this, 'emptyPlaceholder'));
        $this->setPlaceholder('magicSet', array($this, 'emptyPlaceholder'));
        $this->setPlaceholder('magicGet', array($this, 'emptyPlaceholder'));
        $this->setPlaceholder('magicIsset', array($this, 'emptyPlaceholder'));
        $this->setPlaceholder('methods', array($this, 'makeMethods'));
    }
    
    /**
     * Generate a service proxy class based on service configurations
     * and class metadata
     * 
     * @param array $serviceConfig
     * @param ClassMetadata $class
     */
    public function generateServiceProxy($serviceConfig, ClassMetadata $class)
    {
        $this->serviceConfig = $serviceConfig;
        
        return parent::generateProxyClass($class);
    }
    
    public function getProxyClassName($className)
    {
        return ClassUtils::generateProxyClassName($className, $this->proxyNamespace);
    }
    
    /**
     * Generates the magic setter (currently unused)
     *
     * @param  \Doctrine\Common\Persistence\Mapping\ClassMetadata $class
     *
     * @return string
     */
    public function emptyPlaceholder(ClassMetadata $class)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function makeMethods(ClassMetadata $class)
    {
        $methods            = '';
        $methodNames        = array();
        $invokeParams       = array();
        $reflectionMethods  = $class->getReflectionClass()->getMethods();

        foreach ($reflectionMethods as $method) {
            
            $name = $method->getName();
            
            if (
                $method->isConstructor()
                || isset($methodNames[$name])
                || (substr($name, 0, 2) == '__')
                || $method->isFinal()
                || $method->isStatic()
            ) {
                continue;
            }
            
            $config = $this->getOperationConfig($name);

            $methodNames[$name] = true;
            $methods .= "\n    /**\n"
                . "     * {@inheritDoc}\n"
                . "     */\n"
                . '    public function ';

            if ($method->returnsReference()) {
                $methods .= '&';
            }

            $methods .= $name . '(';
            $firstParam = true;
            $parameterString = $argumentString = '';
            $parameters = array();
            $paramNames = array();
            
            foreach ($method->getParameters() as $key => $param) {
                if ($firstParam) {
                    $firstParam = false;
                } else {
                    $parameterString .= ', ';
                    $argumentString  .= ', ';
                }

                $paramClass = $param->getClass();

                // We need to pick the type hint class too
                if (null !== $paramClass) {
                    $parameterString .= '\\' . $paramClass->getName() . ' ';
                } elseif ($param->isArray()) {
                    $parameterString .= 'array ';
                }

                if ($param->isPassedByReference()) {
                    $parameterString .= '&';
                }

                $paramNames[]     = $param->getName();
                $parameters[]     = '$' . $param->getName();
                $parameterString .= '$' . $param->getName();
                $argumentString  .= '$' . $param->getName();

                if ($param->isDefaultValueAvailable()) {
                    $defaultValue = var_export($param->getDefaultValue(), true);
                    $parameterString .= ' = ' . $defaultValue;
                } else {
                    $defaultValue = 'null';
                }
            }

            $methods .= $parameterString . ')';
            $methods .= "\n" . '    {' . "\n";
            $methods .= $this->generateEventCode($name, $paramNames, self::EVENT_PRE);
            $methods .= "\n        \$result = parent::" . $name . '(' . $argumentString . ');' . "\n";
            $methods .= $this->generateEventCode($name, $paramNames, self::EVENT_POST);
            $methods .= "\n" . '    }' . "\n";
        }
        
        // Loop through all PUBLIC methods this time to generate invoke mapping
        $reflectionMethods  = $class->getReflectionClass()->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($reflectionMethods as $method) {
            
            $name = $method->getName();
            $invokeParams[$name] = ['assoc' => [], 'numeric' => []];
            
            foreach ($method->getParameters() as $key => $param) {
                if ($param->isDefaultValueAvailable()) {
                    $defaultValue = var_export($param->getDefaultValue(), true);
                } else {
                    $defaultValue = 'null';
                }
                
                $invokeParams[$name]['assoc'][] = '$command->getParam("'.$param.'", '.$defaultValue.')';
                $invokeParams[$name]['numeric'][] = '$command->getParam("'.$key.'", '.$defaultValue.')';
            }
        }
        
        $invokeImpl = <<<'EOT'
    /**
     * @param  \ValoSo\Command\CommandInterface $command Command to execute
     * @return mixed
     */
    public function __invoke(\ValoSo\Command\CommandInterface $command)
    {

EOT;
        
        $invokeImpl .= 
        '        if (!in_array($command->getOperation(), ["' . implode('","', array_keys($methodNames)) . '"]))'. "\n" .
       "        {\n".
       '            throw new \ValuSo\Exception\OperationNotFoundException(' . "\n" .
       '                sprintf("Service \'%s\' doesn\'t provide operation \'%s\'", $command->getServiceId(), $command->getOperation()));'. "\n" .
       '        }' . "\n\n";
        
        $invokeImpl .= '        $isAssoc = !$command->hasParam(0);' . "\n";
        $invokeImpl .= '        switch ($command->getOperation) {' . "\n";
        
        foreach ($invokeParams as $methodName => $params) {
            $invokeImpl .= '            case "'.$methodName.'":' . "\n";
            
            if (!sizeof($params['assoc'])) {
                $invokeImpl .= '                return $this->' . $methodName . "();\n";
            } else {
                    
                $invokeImpl .= '                if ($isAssoc) {' . "\n"
                            .  '                return $this->' . $methodName . '(' . implode(', ', $params['assoc']) . ");\n"
                            .  '                } else {' . "\n"
                            .  '                return $this->' . $methodName . '(' . implode(', ', $params['numeric']) . ");\n"
                            .  '                }' . "\n";
            }
            
            $invokeImpl .= '                break;' . "\n";
        }
        
        $invokeImpl .= "        }\n"; // end switch
        $invokeImpl .= "    }\n"; // end __invoke
        
        $methods .= "\n\n" . $invokeImpl;

        return $methods;
    }
    
    protected function getOperationConfig($name)
    {
        return isset($this->serviceConfig['operations'][$name])
            ? $this->serviceConfig['operations'][$name] : null;
    }
    
    protected function generateEventCode($operationName, $params, $type)
    {
        $config = $this->getOperationConfig($operationName);
        $code = '';
        
        if (!empty($config['events'])) {
            foreach ($config['events'] as $specs) {
                if ($specs['type'] === $type) {
                    
                    if (is_string($specs['args'])) {
                        $specs['args'] = array($specs['args']);
                    }
                    
                    if (!$specs['name']) {
                        $specs['name'] = $type . '.' . $this->serviceConfig['name'] . '.' . $operationName; 
                    }
                    
                    $code .= '        $__event_params = new ArrayObject();' . "\n";
                    
                    foreach ($params as $name) {
                        if ($specs['args'] === null || in_array($name, $specs['args'])) {
                            $code .= '        $__event_params["'.$name.'"] = $'.$name.';' . "\n";
                        }
                    }
                    
                    $code .= '        $this->getEventManager()->trigger("'.$specs['name'].'", $this, $__event_params);' . "\n";
                    $code .= '        unset($__event_params);' . "\n";
                }
            }
            
            return $code;
        } else {
            return null;
        }
    }
}
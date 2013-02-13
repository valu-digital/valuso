<?php
namespace ValuSo\Proxy;

use ValuSo\Command\CommandInterface;
use ValuSo\Exception;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Reflection\MethodReflection;
use \ReflectionClass;
use \ReflectionMethod;

class ServiceProxyGenerator
{
    const DEFAULT_SERVICE_PROXY_NS = 'ValuSoProxy\\Proxy';
    
    const EVENT_PRE = 'pre';
    
    const EVENT_POST = 'post';
    
    const MARKER = '__CG__';

    /**
     * Service configuration
     * 
     * @var unknown_type
     */
    private $serviceConfig;
    
    /**
     * @var string The namespace that contains all proxy classes.
     */
    private $proxyNamespace;

    /**
     * @var string The directory that contains all proxy classes.
     */
    private $proxyDirectory;
    
    /**
     * @see Doctrine\Common\Proxy\ProxyGenerator::__construct()
     */
    public function __construct($proxyDir = null, $proxyNs = null)
    {
        $this->proxyDirectory = $proxyDir ?: sys_get_temp_dir();
        $this->proxyNamespace = $proxyNs  ?: static::DEFAULT_SERVICE_PROXY_NS;
    }

    public function generateProxyClass($entity, $config)
    {
        if (!is_object($entity)) {
            if ((is_string($entity) && (!class_exists($entity))) // non-existent class
                    || (!is_string($entity)) // not an object or string
            ) {
                throw new Exception\InvalidArgumentException(sprintf(
                        '%s expects an object or valid class name; received "%s"',
                        __METHOD__,
                        var_export($entity, 1)
                ));
            }
        }
        
        $this->serviceConfig = $config;
        
        $reflection  = new ReflectionClass($entity);
        $className = $reflection->getName();
        
        $class = ClassGenerator::fromArray([
            'name' => $this->getProxyClassName($className),
            'namespace_name' => $this->getProxyNamespace($className),
            'extended_class' => '\\' . $className,
            'implemented_interfaces' => array('\Zend\EventManager\EventManagerAwareInterface'),
            'methods' => $this->generateMethods($reflection),
            'properties' => [
                new PropertyGenerator('__wrappedObject', null, PropertyGenerator::FLAG_PUBLIC),
                new PropertyGenerator('__eventManager', null, PropertyGenerator::FLAG_PRIVATE)
            ]
        ]);
        
        $source = "<?php\n" . $class->generate();
        
        $fileName        = $this->getProxyFileName($className);
        $parentDirectory = dirname($fileName);
        
        if ( ! is_dir($parentDirectory) && (false === @mkdir($parentDirectory, 0775, true))) {
            throw Exception\RuntimeException('Proxy directory '.$parentDirectory.' not found');
        }
        
        if ( ! is_writable($parentDirectory)) {
            throw Exception\RuntimeException('Proxy directory '.$parentDirectory.' is not writable');
        }
        
        $tmpFileName = $fileName . '.' . uniqid('', true);
        file_put_contents($tmpFileName, $source);
        rename($tmpFileName, $fileName);
    }
    
    public function getProxyClassName($className)
    {
        return rtrim($this->proxyNamespace, '\\') . '\\'.self::MARKER.'\\' . ltrim($className, '\\');
    }
    
    public function getProxyFilename($className)
    {
        $baseDirectory = $this->proxyDirectory;
        
        return rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::MARKER
        . str_replace('\\', '', $className) . '.php';
    }

    public function getProxyNamespace($className)
    {
        $proxyClassName = $this->getProxyClassName($className);
        $parts = explode('\\', strrev($proxyClassName), 2);
    
        return strrev($parts[1]);
    }

    /**
     * Generate methods for ClassGenerator
     * 
     * @param ReflectionClass $reflectionClass
     * @return multitype:\Zend\Code\Generator\MethodGenerator
     */
    protected function generateMethods(ReflectionClass $reflectionClass)
    {
        $methods = array();
        $methods[] = $this->generateConstructor($reflectionClass);
        $methods[] = $this->generateInvoker($reflectionClass);
        $methods[] = $this->generateEventManagerSetter($reflectionClass);
        $methods[] = $this->generateEventManagerGetter($reflectionClass);
        $methods[] = $this->generateOperationNotFound($reflectionClass);
        $methods[] = $this->generateMatchContext($reflectionClass);
        
        $methods = array_merge(
            $methods,
            $this->generateServiceMethods($reflectionClass));
        
        return $methods;
    }
    
    /**
     * Generate and retrieve proxy constuctor method
     * 
     * @param ReflectionClass $reflectionClass
     * @return \Zend\Code\Generator\MethodGenerator
     */
    protected function generateConstructor(ReflectionClass $reflectionClass)
    {
        return new MethodGenerator(
            '__construct',
            [new ParameterGenerator('wrappedObject')],
            MethodGenerator::FLAG_PUBLIC,
            'if ($wrappedObject instanceof \ValuSo\Feature\ProxyAwareInterface) {' . "\n" .
            '    $wrappedObject->setServiceProxy($this);' . "\n" .
            '}' . "\n" .
            '$this->__wrappedObject = $wrappedObject;' . "\n"
        );
    }
    
    /**
     * Generate and retrieve invoker method
     * 
     * @return MethodGenerator
     */
    protected function generateInvoker(ReflectionClass $reflectionClass)
    {
        $invokeParams       = array();
        $reflectionMethods  = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        
        // Loop through all PUBLIC methods this time to generate invoke mapping
        foreach ($reflectionMethods as $method) {

            $name = $method->getName();
            
            // This operation is not available
            if (!$this->getOperationConfig($name)) {
                continue;
            }
            
            $invokeParams[$name] = ['assoc' => [], 'numeric' => []];
        
            $index = 0;
            foreach ($method->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $defaultValue = var_export($param->getDefaultValue(), true);
                } else {
                    $defaultValue = 'null';
                }
        
                $invokeParams[$name]['assoc'][] = '$command->getParam("'.$param->getName().'", '.$defaultValue.')';
                $invokeParams[$name]['numeric'][] = '$command->getParam('.$index.', '.$defaultValue.')';
                
                $index++;
            }
        }
        
        // Define body for __invoke
        $invokeImpl =
        'if (!in_array($command->getOperation(), ["' . implode('","', array_keys($invokeParams)) . '"]))'. "\n" .
        "{\n".
        '    $this->__operationNotFound($command);' . "\n" .
        '}' . "\n\n";
        
        $invokeImpl .= '$isAssoc = !$command->hasParam(0);' . "\n";
        $invokeImpl .= 'switch ($command->getOperation) {' . "\n";
        
        foreach ($invokeParams as $methodName => $params) {
            $invokeImpl .= '    case "'.$methodName.'":' . "\n";
            $context     = $this->getOperationConfig($methodName, 'context', '*');
            
            if ($context !== '*') {
                $contexts = (array) $context;
                
                $invokeImpl .= '        if(!$this->__matchContext($command->getContext(), array("'.implode('","', $contexts).'"))) {' . "\n"
                             . '            $this->__operationNotFound($command);' . "\n"
                             . '        }' . "\n";
            }
        
            if (!sizeof($params['assoc'])) {
                $invokeImpl .= '        return $this->' . $methodName . "();\n";
            } else {
        
                $invokeImpl .= '        if ($isAssoc) {' . "\n"
                            .  '            return $this->' . $methodName . '(' . implode(', ', $params['assoc']) . ");\n"
                            .  '        } else {' . "\n"
                            .  '            return $this->' . $methodName . '(' . implode(', ', $params['numeric']) . ");\n"
                            .  '        }' . "\n";
            }
        
            $invokeImpl .= '        break;' . "\n";
        }
        
        $invokeImpl .= "}\n"; // end switch
        
        $mg = new MethodGenerator(
            '__invoke',
            [new ParameterGenerator('command', '\ValuSo\Command\CommandInterface')],
            MethodGenerator::FLAG_PUBLIC,
            $invokeImpl
        );
        
        return $mg;
    }
    
    /**
     * Generate and retrieve setter method for event manager
     * 
     * @param ReflectionClass $reflectionClass
     * @return \Zend\Code\Generator\MethodGenerator
     */
    protected function generateEventManagerSetter(ReflectionClass $reflectionClass)
    {
        return new MethodGenerator(
            'setEventManager',
            [new ParameterGenerator('eventManager', '\Zend\EventManager\EventManagerInterface')],
            MethodGenerator::FLAG_PUBLIC,
            '$this->__eventManager = $eventManager;' . "\n"
        );
    }
    
    /**
     * Generate and retrieve getter method for event manager
     * 
     * @param ReflectionClass $reflectionClass
     * @return \Zend\Code\Generator\MethodGenerator
     */
    protected function generateEventManagerGetter(ReflectionClass $reflectionClass)
    {
        return new MethodGenerator(
            'getEventManager',
            array(),
            MethodGenerator::FLAG_PUBLIC,
            'if (!$this->__eventManager) {' . "\n" .
            '    $this->__eventManager = new \Zend\EventManager\EventManager();' . "\n" .
            '}' . "\n" .
            'return $this->__eventManager;' . "\n"
        );
    }
    
    /**
     * Generate and retrieve service methods
     * 
     * @return array
     */
    protected function generateServiceMethods(ReflectionClass $reflectionClass)
    {
        $methods            = array();
        $methodNames        = array();
        $reflectionMethods  = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $excludePattern     = $this->serviceConfig['exclude_pattern'];
        
        $excludedMethods    = array(
            '__get'    => true,
            '__set'    => true,
            '__isset'  => true,
            '__clone'  => true,
            '__sleep'  => true,
            '__wakeup' => true,
            '__invoke' => true,
        );
        
        foreach ($reflectionMethods as $method) {
        
            $name = $method->getName();
        
            if (
                    $method->isConstructor()
                    || isset($methodNames[$name])
                    || isset($excludedMethods[strtolower($name)])
                    || (substr($name, 0, 2) == '__')
                    || $method->isFinal()
                    || $method->isStatic()
            ) {
                continue;
            }
        
            $methodNames[$name] = true;
            $argumentString     = '';
            $firstParam         = true;
            $parameters         = array();
            $eventConfig        = $this->getOperationConfig($name, 'events');
            $preEventExists     = false;
            $postEventExists    = false;
            
            if ($eventConfig) {
                foreach ($eventConfig as $specs) {
                    if ($specs['type'] == self::EVENT_PRE) {
                        $preEventExists = true;
                    } elseif ($specs['type'] == self::EVENT_POST) {
                        $postEventExists = true;
                    }
                }
            }
            
            foreach ($method->getParameters() as $key => $param) {
        
                if ($firstParam) {
                    $firstParam = false;
                } else {
                    $argumentString  .= ', ';
                }
        
                if ($preEventExists) {
                    $argumentString  .= '$__event_params["' . $param->getName().'"]';
                } else {
                    $argumentString  .= '$' . $param->getName();
                }
                
                $paramClass = $param->getClass();

                // We need to pick the type hint class too
                if (null !== $paramClass) {
                    $parameterType = '\\' . $paramClass->getName();
                } elseif ($param->isArray()) {
                    $parameterType = 'array';
                } else {
                    $parameterType = null;
                }
        
                $parameter = new ParameterGenerator($param->getName(), $parameterType);
        
                if ($param->isDefaultValueAvailable()) {
                    $parameter->setDefaultValue($param->getDefaultValue());
                }
        
                if ($param->isPassedByReference()) {
                    $parameter->setPassedByReference(true);
                }
        
                $parameters[$param->getName()] = $parameter;
            }
            
            $cb     = "\$this->__wrappedObject->" . $name . '(' . $argumentString . ');';
            $source = '';
            
            if ($preEventExists) {
                $source .= $this->generateTriggerEventCode($name, array_keys($parameters), self::EVENT_PRE);
            }
            
            if ($postEventExists) {
                $source .= "\$response = " . $cb . "\n\n";
                $source .= $this->generateTriggerEventCode($name, array_keys($parameters), self::EVENT_POST, $preEventExists);
                $source .= "return \$response;\n";
            } else {
                $source .= "return " . $cb . "\n";
            }
        
            $methods[] = new MethodGenerator($name, $parameters, MethodGenerator::FLAG_PUBLIC, $source);
        }
        
        return $methods;
    }
    
    /**
     * Generate __operationNotFound method
     *
     * @param ReflectionClass $reflectionClass
     * @return \Zend\Code\Generator\MethodGenerator
     */
    protected function generateOperationNotFound(ReflectionClass $reflectionClass)
    {
        return new MethodGenerator(
            '__operationNotFound',
            [new ParameterGenerator('command', '\ValuSo\Command\CommandInterface')],
            MethodGenerator::FLAG_PRIVATE,
            'throw new \ValuSo\Exception\OperationNotFoundException(' . "\n" .
            '    sprintf("Service \'%s\' doesn\'t provide operation \'%s\'", $command->getService(), $command->getOperation()));'. "\n"
        );
    }
    
    /**
     * Generate __matchContext method
     * 
     * @param ReflectionClass $reflectionClass
     * @return \Zend\Code\Generator\MethodGenerator
     */
    protected function generateMatchContext(ReflectionClass $reflectionClass)
    {
        return new MethodGenerator(
            '__matchContext',
            [new ParameterGenerator('command', '\ValuSo\Command\CommandInterface'), 'contexts'],
            MethodGenerator::FLAG_PRIVATE,
            'foreach($contexts as $context) { '. "\n" .
            '    if ($context === $command->getContext()) {'. "\n" .
            '        return true;'. "\n" .
            '    } elseif(substr($context, -1) == "*" && strpos($command->getContext(), substr($context,0,-1)) === 0) {'. "\n" .
            '        return true;'. "\n" .
            "    }\n" .
            "}\n" . 
            "return false;"
        );
    }
    
    /**
     * Get configurations for named operation
     * 
     * @param string $name
     * @return array|null
     */
    protected function getOperationConfig($name, $param = null, $default = null)
    {
        $config = isset($this->serviceConfig['operations'][$name])
            ? $this->serviceConfig['operations'][$name] : null;
        
        if ($param === null || $config === null) {
            return $config;
        } else {
            return isset($config[$param]) ? $config[$param] : $default;
        }
    }
    
    /**
     * Generate event trigger code
     * 
     * @param string $operationName
     * @param array $params
     * @param string $type
     * @return string|NULL
     */
    protected function generateTriggerEventCode($operationName, $params, $type, $paramsExist = false)
    {
        $config   = $this->getOperationConfig($operationName, 'events', array());
        $code     = '';
        $service  = strtolower($this->serviceConfig['name']);
        $responseInjected = false;
        
        if (!empty($config)) {
            foreach ($config as $specs) {
                if ($specs['type'] === $type) {
                    
                    if (is_string($specs['args'])) {
                        $specs['args'] = array($specs['args']);
                    }
                    
                    if (!$specs['name']) {
                        $specs['name'] = $type . '.' . $service . '.' . $operationName; 
                    }
                    
                    $specs['name'] = str_replace('<service>', $service, strtolower($specs['name']));
                    
                    $code .= '// Trigger "'.$type.'" event' . "\n";
                    
                    if (!$paramsExist) {
                        $code .= '$__event_params = new \ArrayObject();' . "\n";
                        
                        foreach ($params as $name) {
                            if ($specs['args'] === null || in_array($name, $specs['args'])) {
                                $code .= '$__event_params["'.$name.'"] = $'.$name.';' . "\n";
                            }
                        }
                        
                        $paramsExist = true;
                    }
                    
                    if ($type == self::EVENT_POST && !$responseInjected) {
                        $code .= '$__event_params["__response"] = $response;' . "\n";
                        $responseInjected = true;
                    }
                    
                    $code .= '$this->getEventManager()->trigger("'.$specs['name'].'", $this, $__event_params);' . "\n";
                }
            }
            
            return $code;
        } else {
            return '';
        }
    }
}
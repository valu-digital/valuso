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
     * Optional suffix for proxy class name
     * 
     * @var string
     */
    private $proxyClassSuffix;
    
    /**
     * Initialize proxy class generator
     * 
     * @param string|null $proxyDir           Directory where proxy class files should be written (default is system temp directory)
     * @param string|null $proxyNs            Proxy class namespace (default is "ValuSoProxy\\Proxy")
     * @param string|null $proxyClassSuffix   Optional suffix for proxy class names, useful e.g. 
     *                                        when testing annotations
     */
    public function __construct($proxyDir = null, $proxyNs = null, $proxyClassSuffix = null)
    {
        $this->proxyDirectory = $proxyDir ?: sys_get_temp_dir();
        $this->proxyNamespace = $proxyNs  ?: static::DEFAULT_SERVICE_PROXY_NS;
        $this->proxyClassSuffix = $proxyClassSuffix;
    }

    /**
     * Generate proxy class for entity
     * 
     * @param mixed $entity
     * @param array $config
     * @throws Exception\InvalidArgumentException
     */
    public function generateProxyClass($entity, $config)
    {
        if (!is_array($config) && !$config instanceof \ArrayAccess) {
            throw new \InvalidArgumentException(
                'Invalid proxy class configuration; array or instance of ArrayAccess expected');
        }
        
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
                new PropertyGenerator('__eventManager', null, PropertyGenerator::FLAG_PRIVATE),
                new PropertyGenerator('__commandStack', array(), PropertyGenerator::FLAG_PRIVATE)
            ]
        ]);
        
        $source = "<?php\n" . $class->generate();
        
        $fileName        = $this->getProxyFileName($className);
        $parentDirectory = $this->proxyDirectory;
        
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
    
    /**
     * Get proxy class name for service class name 
     * 
     * @param string $className
     * @return string
     */
    public function getProxyClassName($className)
    {
        $suffix = ltrim($className, '\\');
        
        if ($this->proxyClassSuffix !== null) {
            $suffix .= $this->proxyClassSuffix;    
        }
        
        return rtrim($this->proxyNamespace, '\\') . '\\'.self::MARKER.'\\' . $suffix;
    }
    
    /**
     * Get proxy class filename for service class name
     * 
     * @param string $className
     * @return string
     */
    public function getProxyFilename($className)
    {
        $baseDirectory = $this->proxyDirectory;
        $suffix = str_replace('\\', '', $className);
        
        if ($this->proxyClassSuffix !== null) {
            $suffix .= $this->proxyClassSuffix;
        }
        
        return rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::MARKER
            . $suffix . '.php';
    }

    /**
     * Create a new instance of the proxy class
     * 
     * @param string $className Service class name
     * @return mixed
     */
    public function createProxyClassInstance($service)
    {
        $file = $this->getProxyFilename(get_class($service));
        
        if (file_exists($file)) {
            require_once $file;
        } else {
            throw new \RuntimeException(
                sprintf('Proxy class for service "%s" has not been initialized', get_class($service)));
        }
        
        $proxyService = $this->getProxyClassName(get_class($service));
        return new $proxyService($service);
    }

    /**
     * Get proxy namespace for service class name
     * 
     * @param string $className
     * @return string
     */
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
        $invokeSpecs        = array();
        $reflectionMethods  = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $methodAliases      = array();
        
        // Loop through all PUBLIC methods this time to generate invoke mapping
        foreach ($reflectionMethods as $method) {

            $name = $method->getName();
            
            // This operation is not available
            if (!$this->getOperationConfig($name)) {
                continue;
            }
            
            $aliases = $this->getOperationConfig($name, 'aliases');
            $aliases[] = $name;
            
            $invokeSpecs[$name] = ['params' => [], 'aliases' => $aliases];
        
            $index = 0;
            foreach ($method->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $defaultValue = var_export($param->getDefaultValue(), true);
                } else {
                    $defaultValue = 'null';
                }
        
                $invokeSpecs[$name]['params'][] = '$command->getParam("'.$param->getName().'", $command->getParam('.$index.', '.$defaultValue.'))';
                
                $index++;
            }
            
            $methodAliases = array_merge($methodAliases, $aliases);
        }
        
        // Define body for __invoke
        $invokeImpl =
        'if (!in_array($command->getOperation(), ["' . implode('","', $methodAliases) . '"])) {'. "\n" .
        '    $this->__operationNotFound($command);' . "\n" .
        '}' . "\n\n";
        
        // Store command to a private stack
        $invokeImpl .= '$this->__commandStack[] = $command;' . "\n\n";
        
        // Define injector for identity
        $invokeImpl .=
        'if ($command->getIdentity() && $this->__wrappedObject instanceof \ValuSo\Feature\IdentityAwareInterface) {'. "\n" .
        '    $this->__wrappedObject->setIdentity($command->getIdentity());' . "\n".
        '}' . "\n\n";
        
        $invokeImpl .= 'switch ($command->getOperation()) {' . "\n";
        
        foreach ($invokeSpecs as $methodName => $specs) {
            
            // Generate case statement for each alias, including the real
            // method name
            foreach ($specs['aliases'] as $methodNameOrAlias) {
                $invokeImpl .= '    case "'.$methodNameOrAlias.'":' . "\n";
            }
            
            // Generate code for context testing
            $contexts = $this->getOperationConfig($methodName, 'contexts', 'native');
            
            if ($contexts !== '*') {
                $contexts = (array) $contexts;
                
                $invokeImpl .= '        if(!$this->__matchContext($command, array("'.implode('","', $contexts).'"))) {' . "\n"
                             . '            throw new \ValuSo\Exception\UnsupportedContextException(' . "\n"
                             . '                sprintf("Operation \'%s\' doesn\'t support context \'%s\'", $command->getOperation(), $command->getContext()));'. "\n"
                             . '        }' . "\n";
            }
        
            // Generate invokation code 
            if (!sizeof($specs['params'])) {
                $invokeImpl .= '        $result = $this->' . $methodName . "();\n";
            } else {
                $invokeImpl .= '        $result = $this->' . $methodName . '(' . implode(', ', $specs['params']) . ");\n";
            }
        
            $invokeImpl .=    '        array_pop($this->__commandStack);' . "\n"
                            . '        return $result;' . "\n"
                            . '        break;' . "\n";
        }
        
        $invokeImpl .= '    default:' . "\n"
                    .  '        array_pop($this->__commandStack);' . "\n"
                    .  '    break;' . "\n";
        
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
        $reflectionMethods  = $reflectionClass->getMethods();
        
        $excludedMethods  = array(
            '__construct' => true,
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
            
            // Generate service methods only for public methods or
            // methods that have events configured 
            if ((!$method->isPublic() && !$eventConfig) || $method->isPrivate()) {
                continue;
            }
            
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
            
            if ($method->isPublic()) {
                $visibility = MethodGenerator::FLAG_PUBLIC;
            } else {
                $visibility = MethodGenerator::FLAG_PROTECTED;
            }
        
            $methods[] = new MethodGenerator($name, $parameters, $visibility, $source);
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
            'if ($command->getContext() === "native") {'. "\n" .
            '    return true;'. "\n" .
            '}'. "\n" .
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
        $serviceId  = strtolower($this->serviceConfig['service_id']);
        $responseInjected = false;
        
        if (!empty($config)) {
            foreach ($config as $specs) {
                if ($specs['type'] === $type) {
                    
                    if (is_string($specs['args'])) {
                        $specs['args'] = array($specs['args']);
                    }
                    
                    if (!$specs['name']) {
                        $specs['name'] = $type . '.' . $serviceId . '.' . $operationName; 
                    }
                    
                    $specs['name'] = str_replace('<service>', $serviceId, strtolower($specs['name']));
                    
                    if (!$paramsExist) {
                        $code .= '$__event_params = new \ArrayObject();' . "\n";
                        
                        foreach ($params as $name) {
                            if ($specs['args'] === null || in_array($name, $specs['args'])) {
                                $code .= '$__event_params["'.$name.'"] = $'.$name.';' . "\n";
                            }
                        }
                        
                        // Overwrite with params
                        if (isset($specs['params']) && is_array($specs['params']) && !empty($specs['params'])) {
                            foreach ($specs['params'] as $name => $value) {
                                $code .= '$__event_params["'.$name.'"] = '.var_export($value, true).';' . "\n";
                            }
                        }
                        
                        $paramsExist = true;
                    }
 
                    if ($type == self::EVENT_POST && !$responseInjected) {
                        $code .= '$__event_params["__response"] = $response;' . "\n";
                        $responseInjected = true;
                    }
                    
                    $code .= '// Trigger "'.$type.'" event' . "\n";
                    $code .= 'if (sizeof($this->__commandStack)) {' . "\n";
                    $code .= '    $__event = new \ValuSo\Broker\ServiceEvent('.var_export($specs['name'], true).', $this->__wrappedObject, $__event_params);' . "\n";
                    $code .= '    $__event->setCommand($this->__commandStack[sizeof($this->__commandStack)-1]);' . "\n";
                    
                    $code .= '    $this->getEventManager()->trigger($__event);' . "\n";
                    $code .= '}' . "\n";
                }
            }
            
            return $code;
        } else {
            return '';
        }
    }
}

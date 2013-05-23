# ValuSo

ValuSo is a Zend Framework 2 module for service oriented application architecture.

## Installation

Use [Composer](http://getcomposer.org/) to install 
[`valu/valuso` package](https://packagist.org/packages/valu/valuso).

```sh
php composer.phar require valu/valuso
```

Type `dev-master`, when asked which version to install. Now it is ready to be used as a library. To use it as a ZF2 module, add `valuso` to your modules in `config/application.config.php`.

## Features

### Convenient and IDE-safe way to use services
Typically service is accessed calling **service()** method of the ServiceBroker class. This method initializes a new Worker object. To skip this initialization, it is possible to call ServiceBroker's **execute()** or **dispatch()** directly.
```php
/* @var $service \ValuUser\Service\UserService */
$service = $serviceBroker->service('User');
$service->create('administrator');
```

### Invoke services via HTTP
ValuSo provides end points (controllers) for HTTP REST and RPC interfaces. The difference between these two interfaces is minor and you should stick to using either one. Both interfaces return responses in JSON format.

**Using RPC interface to find a user:**
```
GET /rpc/group/find/029713b396493b01bfc619a7493e2cba
```

**Using REST interface to lock user account:**
```
POST /rest/user/029713b396493b01bfc619a7493e2cba
X-VALU-OPERATION: lock
```

### Execute batch-operations
Batch operations are important, for performance reasons, especially when the services are invoked via external interface.
```
POST /rest/batch
q: {
	"commands":{
		"cmd1": {"service":"user", "operation":"remove", "query":"$administrator"},
		"cmd1": {"service":"group", "operation":"remove", "query":"/administrators"}
	}
}
```
Response
```
{
	"d": {
		"cmd1": true,
		"cmd2": true
	}
}
```

### Easy to use existing classes as services
ServiceBroker expects that the class registered as a service provides **__invoke()** method. In most cases the classes don't implement this method, which indicates the ServiceBroker (or ServicePluginManager to be exact) that these services should be wrapped with a special **proxy class**. With this feature, it is possible to use almost any existing class as a service.
```php
// Assume that LoggerServiceFactory is registered
// for service locator with service ID 'ZendLogger'
$serviceBroker
	->getLoader()
	->register('ZendLogger', 'Log');

$serviceBroker->service('Log')->err('Something went wrong');
```

### Context-aware services
Often the services need to know, in which context they are executed. Mostly, because some services shouldn't be exposed to external interfaces. Following example demonstrates this by calling **update** operation in unsupported context.
```php
$serviceBroker
	->service('User')
	->context('http-get')
	->update(['name' => 'Mr Smith']);
// Throws UnsupportedContextException
```

### Aspect-oriented programming with annotated services
There are lots of cross-cutting concerns with services. Most services need to trigger events, provide logging and access control mechanism. The best way to achieve these features, without messing the actual business code, is to annotate the operations.
```php
use ValuSo\Annotation as ValuService;
class UserService {

    /**
     * Create user
     * 
     * @ValuService\Context("http-put")
     * @ValuService\Log({"args":"username","level":"info"})
     * @ValuService\Trigger("pre")
     * @ValuService\Trigger("post")
     */
    public function create($username, array $specs)
    {
        // create a new user
    }
}
```

### Multiple respondents for one service
Often there is only one class or closure per service, but the architecture is not limited to that. You can actually register any number of classes/closures for the same service name.
```php
$loader = $serviceBroker->getLoader();
$loader->registerService('HmacAuth', 'Auth', new HmacAuthenticationService());
$loader->registerService('HttpBasicAuth', 'Auth', new HttpBasicAuthenticationService());
$loader->registerService('HttpDigestAuth', 'Auth', new HttpDigestAuthenticationService());

// Authenticate until one of the respondents returns
// either boolean true or false and retrieve that value
$isAuthenticated = $serviceBroker
	->service('Auth')
	->until(function($response) {return is_bool($response);})
	->authenticate($httpRequest)
	->last();
```

### Extend any existing service with your implementation
Usually, if service implementation doesn't support certain operation, it throws **UnsupportedOperationException**. CommandManager doesn't stop execution here, but finds the next registered class/closure for the service and gives it a chance to execute the operation. This feature can be used to extend existing services.
```php
class ExtendedUserService {
    public function findExpiredAccounts() {
        // run some special find operation
    }    
}

$loader = $serviceBroker->getLoader();
$loader->registerService('ExtendedUserService', 'User', new ExtendedUserService());

$serviceBroker->service('User')->findExpiredAccounts();
```

### Listening to services
It is often important to listen to service operations. ServiceBroker provides an instance of EventManager and automatically triggers events before and after operation has been invoked. EventManagerAware service classes can also trigger events of their own.
```php
$serviceBroker
	->getEventManager()
	->attach(
		'post.valuuser.remove',
		function($e) use($serviceBroker) {
            $user = $e->getParam('user');
            
            if ($user->getUsername() === 'administrator') {
                $serviceBroker->service('Group')->remove('/administrators');
            }
		}
	);
```

### Calling services from within service
It is common to use another service from within service class. To make this easy, ValuSo provides ServiceBrokerAware interface. This interface is used to inject ServiceBroker instance. There's also a built-in trait, **ServiceBrokerTrait**, that implements the interface for you.
```php
class UserModuleSetupService 
	implements ServiceBrokerAwareInterface
{
	use ServiceBrokerTrait;

	public function setup()
	{
		// Create administrator
		$this->getServiceBroker()
			->service('User')
			->create('administrator');

		return true;
	}
}
```

### Using proxy() method to call internal method
When service proxy classes are used, the proxy class implements missing __invoke method, maps operation name to internal method name and calls that method. Proxy class actually overrides the default method. Typical proxy method implementation looks something like this:
```php
public function create($name, array $specs = array())
{
    $response = $this->__wrappedObject->create($name, $specs);

    $__event_params = new \ArrayObject();
    $__event_params["name"] = $name;
    $__event_params["specs"] = $specs;
    $__event_params["__response"] = $response;
    // Trigger "post" event
    if (sizeof($this->__commandStack)) {
        $__event = new \ValuSo\Broker\ServiceEvent('post.valuaccount.create', $this->__wrappedObject, $__event_params);
        $__event->setCommand($this->__commandStack[sizeof($this->__commandStack)-1]);
        $this->getEventManager()->trigger($__event);
    }
    return $response;
}
```

As you can see, on the first line it calls the real method implementation and the following code lines define event parameters and finally trigger a 'post' event.

You cannot call these proxy methods normally from within the real class. It is however, made possible via special **proxy()** method. The reason for this is, that without using the proxy() method it is not possible to call another method and still trigger its events etc.

```php
class UserService
{
    public function update($query, array $specs)
    {
        $user = $this->resolveUser($query);
        return $this->proxy()->doUpdate($user, $specs);
    }


    /**
     * @ValuService\Trigger({"type":"post","name":"post.<service>.update"})
     */
    protected function doUpdate(User $user, array $specs)
    {
        // update
    }
}
```



## Service Layer

> Defines an application's boundary with a layer of services that establishes a set of available operations and coordinates the application's response in each operation. - [Randy Stafford, EAA](http://martinfowler.com/eaaCatalog/serviceLayer.html)

In ValuSo the service layer is implemented using service classes or closures. The operations of these services are available via service broker. When an operation is executed, the  service broker calls the registered **closure** or **__invoke** method of the service class. If __invoke is not available, the service class is wrapped with a special **proxy class**, that implements the __invoke method by mapping the operation name to method name.

The consumer of a service doesn't know who actually implements the service and where.

The concept and implementation of the service layer is similar to router/controller interaction in MVC pattern.  However, they operate on different levels. Controller is an endpoint that receives the user’s (or client’s) raw request (e.g. from HTTP). Controller then needs to decide who is actually responsible of processing the request. With service layer available, the controller calls appropriate services and returns response that may actually be an aggregation of multiple responses from different services.

## NoMVC or MVSC

With ValuSo, the developer may choose to ignore the common MVC pattern or extend it with the service layer. ValuSo provides three controllers that usually provide the required functionality for complete (service oriented) applications: 
- **HttpRpcController**, 
- **HttpRestController** and 
- **CliController**. 

All of these controllers are able to transform client’s requests into service calls and service responses into correct HTTP/CLI response format (which is JSON in both cases).

ValuSo is designed to work with applications, where the back end needs to be completely separated from the front end. For this reason the concept of **View** in MVC pattern is often obscure.

## Getting started

There's usually one class per service. The service class is initialized using ZF2's ServiceManager, which means that you need to configure either service class name or factory class name.

Consider following example from **ValuUser** module. The module registers a single service, named simply **User**. Only class name is configured for the service.

```php
<?php
namespace ValuUser;

use Zend\ModuleManager\Feature\ConfigProviderInterface;

class Module implements ConfigProviderInterface
{

    public function getConfig()
    {
        return [
        'valu_so' => [
            'services' => [
                'ValuUser' => [
			        		'class' => 'ValuUser\Service\UserService'
                ]
	        ]
        ]];
    }
}
```

Often the service cannot be initialized without injecting some dependencies or configuring the service. In these scenarios, a service factory should be used to initialize the service (consult Zend Framework's documentation for using ServiceManager).

```php
return [
'valu_so' => [
    'services' => [
        'ValuUser' => [
	        'factory' => 'ValuUser\Service\UserServiceFactory'
        ]
    ]
]];
```

It is also possible to register an instance of **invokable** class or closure. For performance reasons this is usually not recommended. Services should be initialized only when needed.

```php
return [
'valu_so' => [
    'services' => [
        'ValuUser' => [
	        'service' => new ValuUserService()
        ]
    ]
]];
```

## Configuration options

ValuSo configurations are read from *valu_so* configuration namespace.

```php
$config = [
  'valu_so' => [
      // Set true to add main service locator as a peering service manager
      'use_main_locator'   => <true>|<false>, 
      // See Zend\Mvc\Service\ServiceManagerConfig
      'factories'          => [...],
      // See Zend\Mvc\Service\ServiceManagerConfig 
      'invokables'         => [...],
      // See Zend\Mvc\Service\ServiceManagerConfig 
      'abstract_factories' => [...],
      // See Zend\Mvc\Service\ServiceManagerConfig
      'shared'             => [...],
      // See Zend\Mvc\Service\ServiceManagerConfig
      'aliases'            => [...],
      'cache'              => [
          'enabled' => true|false, 
          'adapter' => '<ZendCacheAdapter>', 
          'service' => '<ServiceNameReturningCacheAdapter', 
          <adapterConfig> => <value>...
      ],
      'services' => [
          '<id>' => [
              // Name of the service
              'name'     => '<ServiceName>',
              // [optional] Options passed to service when initialized
              'options'  => [...],
              // [optional] Service class (same as defining it in 'invokables')
              'class'    => '<Class>',
              // [optional] Factory class  (same as  defining it in 'factories')
              'factory'  => '<Class>',
              // [optional] Service object/closure
              'service'  => <Object|Closure>,
              // [optinal] Priority number, defaults to 1, highest number is executed first 
              'priority' => <Priority> 
          ]
      ]
  ]
],
```


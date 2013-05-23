# ValuSo

ValuSo is a Zend Framework 2 module for service oriented application architecture.

## Features

### Convenient and IDE-safe way to invoke services
```php
/* @var $service \ValuUser\Service\UserService */
$service = $serviceBroker->service('User');
$service->create('administrator');
```

### Invoke services via HTTP

Using RPC interface:
```
GET /rpc/group/find/029713b396493b01bfc619a7493e2cba
```

Or using REST interface:
```
POST /rest/user/029713b396493b01bfc619a7493e2cba
X-VALU-OPERATION: lock
```

### Easy to use existing classes as services
```php
// Assume that LoggerServiceFactory is registered
// for service locator with service ID 'ZendLogger'
$serviceBroker
	->getLoader()
	->register('ZendLogger', 'Log');

$serviceBroker->service('Log')->err('Something went wrong');
```

### Context-aware services
```php
$serviceBroker
	->service('User')
	->context('http-get')
	->update(['name' => 'Mr Smith']);
// Throws UnsupportedContextException
```

### Convenience with annotated services
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
```php
$loader = $serviceBroker->getLoader();
$loader->registerService('HmacAuth', 'Auth', new HmacAuthenticationService());
$loader->registerService('HttpBasicAuth', 'Auth', new HttpBasicAuthenticationService());
$loader->registerService('HttpDigestAuth', 'Auth', new HttpDigestAuthenticationService());

// Authenticate until one of the respondents returns
// either boolean true or false
$serviceBroker
	->service('Auth')
	->until(function($response) {return is_bool($response);})
	->authenticate('administrator')
	->last();
```

## Service Layer

> Defines an application's boundary with a layer of services that establishes a set of available operations and coordinates the application's response in each operation. - [Randy Stafford, EAA](http://martinfowler.com/eaaCatalog/serviceLayer.html)

In ValuSo the service layer is implemented using service classes. The interfaces of these services are exposed to other services via service broker. The consumer of a service doesn't know who actually implements the service and where.

The concept and implementation of the service layer is similar to router/controller interaction in MVC pattern.  However, they operate on different levels. Controller is an endpoint that receives the user’s (or client’s) raw request (e.g. from HTTP). Controller then needs to decide who is actually responsible of processing the request. With service layer, the controller calls appropriate services and returns response that may actually be an aggregation of multiple responses from different services.

## NoMVC or MVSC

With ValuSo, the developer may choose to ignore the common MVC pattern or extend it with the service layer. ValuSo provides three controllers that usually provide the required functionality for complete (service oriented) applications: **HttpRpcController**, **HttpRestController** and **CliController**. All of these controllers are able to transform client’s requests into service calls and service responses into correct HTTP/CLI response format.

ValuSo is designed to work with applications, where the back end needs to be completely separated from the front end. For this reason the concept of **View** in MVC pattern is often obscure.

## Getting started

There's usually one class per service. The service class is initialized using ZF2's ServiceManager, which means that you need to configure either service class name or factory class name.

Consider following example from **ValuLog** module. The module registers a single service, named simply **Log**. Only class name is configured for the service.

```php
<?php
namespace ValuLog;

use Zend\ModuleManager\Feature\ConfigProviderInterface;

class Module implements ConfigProviderInterface
{

    public function getConfig()
    {
        return [
        'valu_so' => [
            'services' => [
                'ValuLog' => [
			        		'class' => 'ValuLog\Service\Log'
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
        'ValuLog' => [
	        'factory' => 'ValuLog\Service\LogFactory'
        ]
    ]
]];
```

It is also possible to register an instance of **invokable** class or closure. For performance reasons this is usually not recommended. Services should be initialized only when needed.

```php
return [
'valu_so' => [
    'services' => [
        'ValuLog' => [
	        'service' => new InvokableService()
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

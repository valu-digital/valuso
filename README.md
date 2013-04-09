# ValuSo

ValuSo is a Zend Framework 2 module for service oriented application architecture.

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

It is also possible to register an instance of invokable class or closure. For performance reasons this is usually not recommended. Services should be initialized only when needed.

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
              // [optional] Options passed to service 
							// when initialized
              'options'  => [...],
              // [optional] Service class (same as 
							// defining it in 'invokables')
              'class'    => '<Class>',
              // [optional] Factory class  (same as 
							// defining it in 'factories')
              'factory'  => '<Class>',
              // [optional] Service object/closure
              'service'  => <Object|Closure>,
              // [optinal] Priority number, 
							// defaults to 1, highest 
              // number is executed first 
              'priority' => <Priority> 
          ]
      ]
  ]
]
```




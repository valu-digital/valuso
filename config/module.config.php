<?php
return [
    'service_manager' => [
        'factories' => [
            'ServiceBroker' => 'ValuSo\Broker\ServiceBrokerFactory',
        ]
    ],
    'controllers' => [
        'invokables' => [
            'ValuSoServiceController' => 'ValuSo\Controller\ServiceController',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'service-broker' => 'ValuSo\Controller\Plugin\ServiceBroker',
        ],
    ],
    'router' => [
        'routes' => [
            'valuso-rpc' => [
                'type' => 'Zend\\Mvc\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/api/rpc/[/:service[/:operation]]',
                    'constraints' => [
                        'service' => '[a-zA-Z][\\.a-zA-Z0-9_-]*',
                        'operation' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'valuapp-service-controller',
                        'action' => 'broker',
                        '_acl' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
        'map' => [
            'serviceBroker' => 'ValuSo\Controller\Plugin\ServiceBroker',
        ],
    ],
    'valu_so' => [
        'proxy_dir' => 'data/valuso/proxy',
        'use_main_locator' => true,
        'services' => []
    ]
];

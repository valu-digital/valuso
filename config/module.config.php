<?php
return [
    'service_manager' => [
        'factories' => [
            'ServiceBroker' => 'ValuSo\Broker\ServiceBrokerFactory',
            'valu_so.annotation_builder' => 'ValuSo\Annotation\AnnotationBuilderFactory',
        ]
    ],
    'controllers' => [
        'invokables' => [
            'ValuSoServiceController' => 'ValuSo\Controller\ServiceController',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'service-broker' => 'ValuSo\Controller\Plugin\ServiceBrokerPlugin',
        ],
    ],
    'router' => [
        'routes' => [
            'valuso-rpc' => [
                'type' => 'Zend\\Mvc\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/api/rpc[/:service[/:operation]]',
                    'constraints' => [
                        'service' => '[a-zA-Z][\\.a-zA-Z0-9_-]*',
                        'operation' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'ValuSoServiceController',
                        'action' => 'http',
                        '_authenticate' => true
                    ],
                ],
            ],
            'valuso-rest' => [
                'type' => 'Zend\\Mvc\\Router\\Http\\Regex',
                'options' => [
                    'regex' => '/api/rest/(?<service>[a-zA-Z][\\.a-zA-Z0-9_-]*)(?<path>/.*)?',
                    'constraints' => [
                        'service' => '[a-zA-Z][\\.a-zA-Z0-9_-]*'
                    ],
                    'defaults' => [
                        'controller' => 'ValuSoServiceController',
                        'action' => 'http',
                        '_authenticate' => true
                    ],
                    'spec' => '/rest-api/%service%'
                ],
            ],
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
                'service' => [
                    'options' => [
                        'route'    => 'exec <service> <operation> [<query>] [--verbose|-v] [--user=|u=] [--password=|-p=] [--silent|-s]',
                        'defaults' => [
                            'controller' => 'ValuSoServiceController',
                            'action'     => 'console',
                            'identity'   => [
                                'username' => 'administrator',
                                'superuser' => true,
                                'roles' => ['/' => 'superuser']
                            ],
                            '_authenticate' => true
                        ]
                    ]
                ]
            ]
        ]
    ],
    'valu_so' => [
        'proxy_dir' => 'data/valuso/proxy',
        'use_main_locator' => true,
        'cache' => [
            'adapter' => 'memory',
        ],
        'services' => [
            'ValuSoSetup' => [
                'name' => 'ValuSo.Setup',
                'class' => 'ValuSo\\Service\\SetupService',
            ],
            'ValuSoBatch' => [
                'name' => 'Batch',
                'class' => 'ValuSo\\Service\\BatchService',
            ],
        ]
    ]
];

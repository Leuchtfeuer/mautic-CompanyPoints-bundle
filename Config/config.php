<?php

return [
    'name'        => 'Leuchtfeuer Digital Marketing GmbH',
    'description' => 'Massively enhanced Company-based Scoring',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'menu'        => [
        'main' => [
            'leuchfeuercompany.menu.main' => [
                'id'        => 'mautic_company_points_main_menu',
                //                'route'     => 'mautic_company_points_index',
                //                'access'    => 'tagManager:tagManager:view',
                'iconClass' => 'ri-focus-2-fill',
                'priority'  => 1,
                'checks'    => [
                    'integration' => [
                        'LeuchtfeuerCompanyPoints' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'leuchfeuercompany.menu.managetrigger' => [
                'id'        => 'mautic_company_points_index',
                //            'items' => [
                //                'mautic.company.points' => [
                'route'     => 'mautic_company_pointtrigger_index',
                //                    'access'    => ['lead:leads:viewown', 'lead:leads:viewother'],
                'parent'    => 'leuchfeuercompany.menu.main',
                'checks'    => [
                    'integration' => [
                        'LeuchtfeuerCompanyPoints' => [
                            'enabled' => true,
                        ],
                    ],
                ],
                //                    'priority'  => 50,
                //                ],
            ],
        ],
    ],
    'routes'      => [
        'main' => [
            'mautic_company_points_index' => [
                'path'       => '/company/points',
                'controller' => 'LeuchtfeuerCompanyPointsBundle:CompanyPoints:index',
            ],
            'mautic_company_pointtriggerevent_action' => [
                'path'       => '/company/points/triggers/events/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerEventController::executeAction',
            ],
            'mautic_company_pointtrigger_index' => [
                'path'       => '/company/points/triggers/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
            ],
            'mautic_company_pointtrigger_action' => [
                'path'       => '/company/points/triggers/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::executeAction',
            ],
        ],
    ],
    'services'    => [
        'integrations' => [
            'mautic.integration.leuchtfeuercompanypoints' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanypoints.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanypoints.config' => [
                'class'     => MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
                'tags'  => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
];

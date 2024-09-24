<?php

return [
    'name'        => 'Company Points by Leuchtfeuer',
    'description' => 'Massively enhanced Company-based Scoring',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
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
    'menu'        => [
        'main' => [
            'leuchfeuercompany.menu.managetrigger' => [
//                'id'        => 'mautic_company_pointtrigger_index',
                'parent'    => 'mautic.companies.menu.index',
                'route'     => 'mautic_company_pointtrigger_index',
                'priority'  => 10,
                'checks'    => [
                    'integration' => [
                        'LeuchtfeuerCompanyPoints' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'mautic.companies.menu.sub.index' => [
                'id'        => 'mautic.companies.menu.index',
                'parent'    => 'mautic.companies.menu.index',
                'route'     => 'mautic_company_index',
                'access'    => ['lead:leads:viewother'],
                'priority'  => 100,
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

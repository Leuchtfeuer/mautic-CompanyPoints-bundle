<?php

return [
    'name'        => 'Leuchtfeuer Digital Marketing GmbH',
    'description' => 'Massively enhanced Company-based Scoring',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
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

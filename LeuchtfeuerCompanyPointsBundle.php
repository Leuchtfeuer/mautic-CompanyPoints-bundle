<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\InstallHelper;

class LeuchtfeuerCompanyPointsBundle extends AbstractPluginBundle
{
    public const FIELD_DATA = [
        'alias' => 'score_calculated',
        'name'  => 'Score Calculated',
        'type'  => 'number',
    ];

    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        InstallHelper::installField($factory, (string) $factory->getParameter('mautic.db_table_prefix'), self::FIELD_DATA['alias'], self::FIELD_DATA['name'], self::FIELD_DATA['type']);
        parent::onPluginInstall($plugin, $factory, $metadata, $installedSchema);
    }
}

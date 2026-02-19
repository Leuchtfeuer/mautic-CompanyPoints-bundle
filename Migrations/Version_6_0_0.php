<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_6_0_0 extends AbstractMigration
{
    private string $companyPointsTable = 'company_points';
    private string $companyPointLogTable = 'company_point_company_action_log';


    protected function isApplicable(Schema $schema): bool
    {
        try {
            $pointsTable = $this->concatPrefix($this->companyPointsTable);
            $pointLogTable = $this->concatPrefix($this->companyPointLogTable);

            if (!$schema->hasTable($pointsTable) || !$schema->hasTable($pointLogTable)) {
                return false;
            }
            return true;
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("DROP TABLE IF EXISTS `{$this->concatPrefix('company_point_company_action_log')}`");
        $this->addSql("DROP TABLE IF EXISTS `{$this->concatPrefix('company_points')}`");
    }
}

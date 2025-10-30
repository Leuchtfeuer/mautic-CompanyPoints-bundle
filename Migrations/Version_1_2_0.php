<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_2_0 extends AbstractMigration
{
    private string $companyTriggersTable = 'company_point_triggers';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $tableName = $this->concatPrefix($this->companyTriggersTable);

            if (!$schema->hasTable($tableName)) {
                return false;
            }

            $table = $schema->getTable($tableName);

            return !$table->hasColumn('type') || $table->hasColumn('member_activity');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->companyTriggersTable)}` ADD type VARCHAR(191) NOT NULL");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->companyTriggersTable)}` ADD member_activity VARCHAR(191) DEFAULT NULL;");

        // Set the `type` for all existing records to 'points'
        $this->addSql("UPDATE `{$this->concatPrefix($this->companyTriggersTable)}` SET type = 'points'");
    }
}

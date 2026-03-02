<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Entity\AuditLog;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support\ActivePluginTrait;

class AuditLogSubscriberTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;

    private AuditLogModel $auditLogModel;
    private CompanyTriggerModel $companyTriggerModel;

    private int $baseTotalRows = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->auditLogModel = self::getContainer()->get('mautic.core.model.auditlog');
        assert($this->auditLogModel instanceof AuditLogModel);
        $this->companyTriggerModel = self::getContainer()->get('mautic.companypoint.model.trigger');
        assert($this->companyTriggerModel instanceof CompanyTriggerModel);
        $this->baseTotalRows = count($this->auditLogModel->getRepository()->findAll());
    }

    public function testAuditLogSubscriber(): void
    {
        $companyTriggerEntity = $this->createCompanyPointsTriggerCreatingNewAuditLog();
        $companyTriggerEntity = $this->updateCompanyPointsTriggerCreatingNewAuditLog($companyTriggerEntity);
        $this->deleteCompanyPointsTriggerCreatingNewAuditLog($companyTriggerEntity);
    }

    private function createCompanyPointsTriggerCreatingNewAuditLog(): CompanyTrigger
    {
        $auditLogsBefore = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows, $auditLogsBefore);
        $companyTriggerEntity = new CompanyTrigger();
        $name                 = 'Test Trigger';
        $companyTriggerEntity->setName($name);
        $companyTriggerEntity->setDescription('This is a test trigger');
        $companyTriggerEntity->setPoints(10);
        $companyTriggerEntity->setIsPublished(true);
        $this->companyTriggerModel->saveEntity($companyTriggerEntity);
        $auditLogsAfter = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows + 1, $auditLogsAfter);
        $lastAuditLog = end($auditLogsAfter);
        assert($lastAuditLog instanceof AuditLog);
        self::assertSame('company_point_trigger', $lastAuditLog->getObject());
        self::assertSame('added', $lastAuditLog->getAction());
        self::assertStringContainsString($name, json_encode($lastAuditLog->getDetails()));

        return $companyTriggerEntity;
    }

    private function updateCompanyPointsTriggerCreatingNewAuditLog(CompanyTrigger $companyTriggerEntity): CompanyTrigger
    {
        $auditLogsBefore = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows + 1, $auditLogsBefore);
        $companyTriggerEntity->setName('Updated Trigger Name');
        $companyTriggerEntity->setPoints(5);
        $this->companyTriggerModel->saveEntity($companyTriggerEntity);
        $auditLogsAfterUpdate = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows + 2, $auditLogsAfterUpdate);
        $lastAuditLogUpdate = end($auditLogsAfterUpdate);
        assert($lastAuditLogUpdate instanceof AuditLog);
        self::assertSame('company_point_trigger', $lastAuditLogUpdate->getObject());
        self::assertSame('updated', $lastAuditLogUpdate->getAction());
        self::assertStringContainsString('Updated Trigger Name', json_encode($lastAuditLogUpdate->getDetails()));
        self::assertStringContainsString('name', json_encode($lastAuditLogUpdate->getDetails()));
        self::assertStringContainsString('object_description', json_encode($lastAuditLogUpdate->getDetails()));

        return $companyTriggerEntity;
    }

    private function deleteCompanyPointsTriggerCreatingNewAuditLog(CompanyTrigger $companyTriggerEntity): CompanyTrigger
    {
        $auditLogsBefore = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows + 2, $auditLogsBefore);
        $this->companyTriggerModel->deleteEntity($companyTriggerEntity);
        $auditLogsAfterDelete = $this->auditLogModel->getRepository()->findAll();
        self::assertCount($this->baseTotalRows + 3, $auditLogsAfterDelete);
        $lastAuditLogDelete = end($auditLogsAfterDelete);
        assert($lastAuditLogDelete instanceof AuditLog);
        self::assertSame('company_point_trigger', $lastAuditLogDelete->getObject());
        self::assertSame('deleted', $lastAuditLogDelete->getAction());

        return $companyTriggerEntity;
    }
}

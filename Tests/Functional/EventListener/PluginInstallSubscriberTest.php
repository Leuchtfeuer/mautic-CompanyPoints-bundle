<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\IntegrationsBundle\Integration\Interfaces\IntegrationInterface;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use Mautic\PluginBundle\Helper\IntegrationHelper;
class PluginInstallSubscriberTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);

        // Clean up orphaned column from previous test runs
        try {
            $this->connection->executeStatement('ALTER TABLE test_companies DROP COLUMN companyscore_calculated');
        } catch (\Exception $e) {
        }
    }

    public function testPluginInstallSubscriber(): void
    {
        $this->enablePlugin(true);
        $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanyPointsBundle']);
        $leadFieldModel = $this->getContainer()->get('mautic.lead.model.field');
        assert($leadFieldModel instanceof \Mautic\LeadBundle\Model\FieldModel);
        $allFields = $leadFieldModel->getRepository()->findBy(['object' => 'company']);
        $scoreCalculatedField = $leadFieldModel->getRepository()->findOneBy(['alias' => 'companyscore_calculated']);
        self::assertNotNull($scoreCalculatedField, 'Field should be created by ON_PLUGIN_INSTALL event');
        self::assertSame('core', $scoreCalculatedField->getGroup());
    }

    private function enablePlugin(bool $enable): void
    {
        $pluginInstaller = self::getContainer()->get(ReloadFacade::class);
        assert($pluginInstaller instanceof ReloadFacade);
        $pluginInstaller->reloadPlugins();
        $integrationHelper = self::getContainer()->get(IntegrationHelper::class);
        assert($integrationHelper instanceof IntegrationHelper);
        $integration = $integrationHelper->getIntegrationObject('LeuchtfeuerCompanyPoints');
        assert($integration instanceof IntegrationInterface);
        $integration->getIntegrationConfiguration()->setIsPublished($enable);
        $doctrine = self::getContainer()->get('doctrine');
        assert($doctrine instanceof ManagerRegistry);
        $doctrine->getManager()->flush();
    }
}

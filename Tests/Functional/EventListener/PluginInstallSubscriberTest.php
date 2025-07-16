<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;

class PluginInstallSubscriberTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $nameBundle  = 'LeuchtfeuerCompanyPointsBundle';
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerCompanyPointsIntegration::INTEGRATION_NAME]);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName(str_replace('Bundle', '', $nameBundle));
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);

        $nameBundle2      = 'LeuchtfeuerCompanyTagsBundle';
        $nameIntegration2 = 'LeuchtfeuerCompanyTags';
        $integration2     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration2]);
        if (empty($integration2)) {
            $plugin2      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle2]);
            $integration2 = new Integration();
            $integration2->setName(str_replace('Bundle', '', $nameBundle));
            $integration2->setPlugin($plugin2);
        }
        $integration2->setIsPublished($isPublished);
        $this->em->persist($integration2);

        $this->em->flush();
    }

    public function testPluginInstallSubscriber(): void
    {
        $leadFieldModel = $this->getContainer()->get('mautic.lead.model.field');
        assert($leadFieldModel instanceof \Mautic\LeadBundle\Model\FieldModel);
        $scoreCalculatedField = $leadFieldModel->getRepository()->findOneBy(['alias' => 'companyscore_calculated']);
        self::assertSame('core', $scoreCalculatedField->getGroup());
    }
}

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
        $this->useCleanupRollback = false;
        $this->activePlugin();
        $this->setUpSymfony($this->configParams);
    }

    private function activePlugin(): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $this->em->clear();
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

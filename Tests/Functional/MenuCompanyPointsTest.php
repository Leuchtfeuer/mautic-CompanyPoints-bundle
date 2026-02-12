<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support\ActivePluginTrait;

class MenuCompanyPointsTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;
    private FunctionalFixtureHelper $fixtureHelper;
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);

        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->fixtureHelper->loginAdmin();
    }

    public function testMenu(): void
    {
        $crawler = $this->client->request('GET', '/s/companies');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}

<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
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

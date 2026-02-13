<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support\ActivePluginTrait;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyTriggerControllerTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;
    private FunctionalFixtureHelper $fixtureHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);

        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $this->loginUser($user);
    }

    public function testIndexAction(): void
    {
        $this->client->request('GET', '/s/company/points/triggers');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testNewAction(): void
    {
        $this->client->request('GET', '/s/company/points/triggers/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testNewActionWithEvent(): void
    {
        $companyTrigger      = new CompanyTrigger();
        $companyTrigger->setName('Test Trigger');
        $companyTrigger->setDescription('Test Description');
        $companyTrigger->setPoints(10);
        $companyTrigger->setColor('000000');
        $companyTrigger->setIsPublished(true);
        $this->em->persist($companyTrigger);
        $this->em->flush();

        $id = $companyTrigger->getId();

        $companyTags         = $this->createCompanyTags();
        $companyTrigger      = $this->em->getRepository(CompanyTrigger::class)->find($id);
        $companytriggerEvent = new CompanyTriggerEvent();
        $companytriggerEvent->setTrigger($companyTrigger);
        $companytriggerEvent->setName('Event company tags one');
        $companytriggerEvent->setDescription('Description event company tags one');
        $companytriggerEvent->setType('companytags.updatetags');
        $companytriggerEvent->setOrder(1);
        $companytriggerEvent->setProperties([
            'add_tags'    => [$companyTags[0]->getTag(), $companyTags[1]->getTag()],
            'remove_tags' => [],
        ]);
        $this->em->persist($companytriggerEvent);
        $companyTrigger->addTriggerEvent('companytags.updatetags1', $companytriggerEvent);
        $this->em->persist($companyTrigger);
        $this->em->flush();
        $crawlerEdit = $this->client->request('GET', '/s/company/points/triggers/edit/'.$id);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Event company tags one', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Description event company tags one', $this->client->getResponse()->getContent());
    }

    private function createCompanyTags()
    {
        $companyTag = new CompanyTags();
        $companyTag->setTag('Test Tag');
        $companyTag->setDescription('Description Tag');
        $companyTag2 = new CompanyTags();
        $companyTag2->setTag('Test2 Tag');
        $companyTag2->setDescription('Description2 Tag');
        $this->em->persist($companyTag);
        $this->em->persist($companyTag2);
        $this->em->flush();

        return [$companyTag, $companyTag2];
    }
}

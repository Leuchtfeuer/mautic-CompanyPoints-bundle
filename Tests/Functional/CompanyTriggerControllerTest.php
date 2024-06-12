<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PointBundle\Entity\TriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;

class CompanyTriggerControllerTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
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


    public function testEditAction(): void
    {
        $crawler = $this->client->request('GET', '/s/company/points/triggers/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->filter('form[name=companypointtrigger]')->form();
        $fieldValues = $form->getPhpValues();
        $fieldValues['companypointtrigger']['name'] = 'Test Trigger';
        $fieldValues['companypointtrigger']['description'] = 'Test Description';
        $fieldValues['companypointtrigger']['points'] = 10;
        $fieldValues['companypointtrigger']['color'] = '000000';
        $fieldValues['companypointtrigger']['isPublished'] = true;
        $form->setValues($fieldValues);
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('has been created!', $crawler->html());
        $this->client->request('GET', '/s/company/points/triggers');
        $this->assertStringContainsString('Test Trigger', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Test Description', $this->client->getResponse()->getContent());
    }

    public function testNewActionWithEvent(): void
    {
        $crawlerEvent = $this->client->request('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId=mautic_bc');

        $crawler = $this->client->request('GET', '/s/company/points/triggers/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->filter('form[name=companypointtrigger]')->form();
        $fieldValues = $form->getPhpValues();
        $fieldValues['companypointtrigger']['name'] = 'Test Trigger';
        $fieldValues['companypointtrigger']['description'] = 'Test Description';
        $fieldValues['companypointtrigger']['points'] = 10;
        $fieldValues['companypointtrigger']['color'] = '000000';
        $fieldValues['companypointtrigger']['isPublished'] = true;
//        $fieldValues['companypointtrigger']['event'] = 'mautic.point.trigger_executed';
        $form->setValues($fieldValues);
        $crawler = $this->client->submit($form);
        $editUrl = $crawler->filter('form[name=companypointtrigger]')->attr('action');
        $id = explode('/', $editUrl);
        $id = end($id);
//        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
//        $this->assertStringContainsString('has been created!', $crawler->html());
//        $this->client->request('GET', '/s/company/points/triggers');
//        $this->assertStringContainsString('Test Trigger', $this->client->getResponse()->getContent());
//        $this->assertStringContainsString('Test Description', $this->client->getResponse()->getContent());
        $companyTags = $this->createCompanyTags();
        $companyTrigger = $this->em->getRepository(CompanyTrigger::class)->find($id);
        $companytriggerEvent = new CompanyTriggerEvent();
        $companytriggerEvent->setTrigger($companyTrigger);
        $companytriggerEvent->setName('Event company tags one');
        $companytriggerEvent->setDescription('Description event company tags one');
        $companytriggerEvent->setType('companytags.updatetags');
        $companytriggerEvent->setProperties([
            'add_tags'    => [$companyTags[0]->getId(),$companyTags[1]->getId()],
            'remove_tags' => [],
        ]);
        $this->em->persist($companytriggerEvent);
        $this->em->flush();
        $this->client->request('GET', '/s/company/points/triggers/edit/'.$id);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Event company tags one', $this->client->getResponse()->getContent());
//        $this->assertStringContainsString('Description event company tags one', $this->client->getResponse()->getContent());
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

        $nameBundle2  = 'LeuchtfeuerCompanyTagsBundle';
        $nameIntegration2 = 'LeuchtfeuerCompanyTags';
        $integration2 = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration2]);
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

}
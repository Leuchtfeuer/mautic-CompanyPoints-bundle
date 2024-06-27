<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerLog;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyTriggerControllerSendEmailEventTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testCreateActionWithEventSendEmail()
    {
        $crawlerEvent = $this->client->request('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId=mautic_bc');

        $crawler = $this->client->request('GET', '/s/company/points/triggers/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $form                                              = $crawler->filter('form[name=companypointtrigger]')->form();
        $fieldValues                                       = $form->getPhpValues();
        $fieldValues['companypointtrigger']['name']        = 'Test Trigger';
        $fieldValues['companypointtrigger']['description'] = 'Test Description';
        $fieldValues['companypointtrigger']['points']      = 10;
        $fieldValues['companypointtrigger']['color']       = '000000';
        $fieldValues['companypointtrigger']['isPublished'] = true;
        $form->setValues($fieldValues);
        $crawler = $this->client->submit($form);
        $editUrl = $crawler->filter('form[name=companypointtrigger]')->attr('action');
        $id      = explode('/', $editUrl);
        $id      = end($id);

        $companyTags         = $this->createCompanyTags();
        $companyTrigger      = $this->em->getRepository(CompanyTrigger::class)->find($id);
        $emailTemplate       = new \Mautic\EmailBundle\Entity\Email();
        $emailTemplate->setName('Test Email Template');
        $emailTemplate->setSubject('Test Subject');
        $emailTemplate->setContent('Test Content');
        $emailTemplate->setCreatedBy($this->em->getRepository(User::class)->find(1));
        $this->em->persist($emailTemplate);
        $this->em->flush();
        $companytriggerEvent = new CompanyTriggerEvent();
        $companytriggerEvent->setTrigger($companyTrigger);
        $companytriggerEvent->setName('Event company tags one');
        $companytriggerEvent->setDescription('Description event company tags one');
        $companytriggerEvent->setType('companytags.sendemails');
        $companytriggerEvent->setOrder(1);
        $companytriggerEvent->setProperties([
            'subject' => 'Test Subject',
            'message' => 'Test Message',
            'user_id' => [
                1, 2,
            ],
            'email_to_owner' => true,
            'to'             => 'test@test.com',
            'bcc'            => 'test@test.com',
            'cc'             => 'test@test.com',
            'templates'      => $emailTemplate->getId(),
        ]);
        $this->em->persist($companytriggerEvent);
        $companyTrigger->addTriggerEvent('companytags.sendemails', $companytriggerEvent);
        $this->em->persist($companyTrigger);
        $this->em->flush();

        $crawlerEdit = $this->client->request('GET', '/s/company/points/triggers/edit/'.$id);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Event company tags one', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Description event company tags one', $this->client->getResponse()->getContent());
    }

    public function testNewActionWithEventSendEmailAndAddNewCompanyAndRunATrigger(): void
    {
        $crawlerEvent = $this->client->request('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId=mautic_bc');

        $crawler = $this->client->request('GET', '/s/company/points/triggers/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $form                                              = $crawler->filter('form[name=companypointtrigger]')->form();
        $fieldValues                                       = $form->getPhpValues();
        $fieldValues['companypointtrigger']['name']        = 'Test Trigger';
        $fieldValues['companypointtrigger']['description'] = 'Test Description';
        $fieldValues['companypointtrigger']['points']      = 10;
        $fieldValues['companypointtrigger']['color']       = '000000';
        $fieldValues['companypointtrigger']['isPublished'] = true;
        $form->setValues($fieldValues);
        $crawler = $this->client->submit($form);
        $editUrl = $crawler->filter('form[name=companypointtrigger]')->attr('action');
        $id      = explode('/', $editUrl);
        $id      = end($id);

        $companyTags         = $this->createCompanyTags();
        $companyTrigger      = $this->em->getRepository(CompanyTrigger::class)->find($id);
        $emailTemplate       = new \Mautic\EmailBundle\Entity\Email();
        $emailTemplate->setName('Test Email Template');
        $emailTemplate->setSubject('Test Subject');
        $emailTemplate->setContent('Test Content');
        $emailTemplate->setCreatedBy($this->em->getRepository(User::class)->find(1));
        $this->em->persist($emailTemplate);
        $this->em->flush();
        $companytriggerEvent = new CompanyTriggerEvent();
        $companytriggerEvent->setTrigger($companyTrigger);
        $companytriggerEvent->setName('Event company tags one');
        $companytriggerEvent->setDescription('Description event company tags one');
        $companytriggerEvent->setType('companytags.sendemails');
        $companytriggerEvent->setOrder(1);
        $companytriggerEvent->setProperties([
            'subject' => 'Test Subject',
            'message' => 'Test Message',
            'user_id' => [
                1, 2,
            ],
            'email_to_owner' => true,
            'to'             => 'test@test.com',
            'bcc'            => 'test@test.com',
            'cc'             => 'test@test.com',
            'templates'      => $emailTemplate->getId(),
        ]);
        $this->em->persist($companytriggerEvent);
        $companyTrigger->addTriggerEvent('companytags.sendemails', $companytriggerEvent);
        $this->em->persist($companyTrigger);
        $this->em->flush();

        $crawlerEdit = $this->client->request('GET', '/s/company/points/triggers/edit/'.$id);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Event company tags one', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Description event company tags one', $this->client->getResponse()->getContent());
        $companyTriggerLogModel      = $this->em->getRepository(CompanyTriggerLog::class)->findAll();
        $this->assertEmpty($companyTriggerLogModel);
        $company = $this->createCompany();
        $crawler = $this->client->request('GET', '/s/companies/new/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $form                                     = $crawler->filter('form[name=company]')->form();
        $fieldValues                              = $form->getValues();
        $fieldValues['company[score_calculated]'] = 21;
        $fieldValues['company[companyname]']      = 'Test New Company';
        $form->setValues($fieldValues);
        $crawler                     = $this->client->submit($form);
        $companyTriggerLogModel      = $this->em->getRepository(CompanyTriggerLog::class)->findAll();
        $this->assertNotEmpty($companyTriggerLogModel);
        $this->assertEquals(1, count($companyTriggerLogModel));
        $this->assertEquals('Test Trigger', $companyTriggerLogModel[0]->getEvent()->getTrigger()->getName());
        $this->assertEquals('Test Description', $companyTriggerLogModel[0]->getEvent()->getTrigger()->getDescription());
        $this->assertEquals(10, $companyTriggerLogModel[0]->getEvent()->getTrigger()->getPoints());
        $this->assertEquals('Event company tags one', $companyTriggerLogModel[0]->getEvent()->getName());
        $this->assertEquals('Description event company tags one', $companyTriggerLogModel[0]->getEvent()->getDescription());
        $this->assertEquals('companytags.sendemails', $companyTriggerLogModel[0]->getEvent()->getType());
        $this->assertEquals('Test Subject', $companyTriggerLogModel[0]->getEvent()->getProperties()['subject']);
        $this->assertEquals('Test Message', $companyTriggerLogModel[0]->getEvent()->getProperties()['message']);
        $this->assertEquals([1, 2], $companyTriggerLogModel[0]->getEvent()->getProperties()['user_id']);
        $this->assertEquals(true, $companyTriggerLogModel[0]->getEvent()->getProperties()['email_to_owner']);
    }

    private function createCompany()
    {
        $company = new Company();
        $company->setName('Test Company');
        $company->setOwner($this->em->getRepository(User::class)->find(1));
        $this->em->persist($company);
        $this->em->flush();

        return $company;
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
}

<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support\ActivePluginTrait;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use Symfony\Component\DomCrawler\Crawler;

class AjaxControllerTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);

        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $this->loginUser($user);
    }

    public function testViewEventAddCompanyTag(): void
    {
        $companyTrigger = $this->newCompanyTrigger('Test Trigger', 'Test Description', 10, 'aaaccc');
        $companyTags    = $this->createCompanyTags();
        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId='.$companyTrigger->getId());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Modify Company Tags', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Add Company Tags', $this->client->getResponse()->getContent());
    }

    public function testNewEventAddCompanyTag(): void
    {
        $companyTrigger = $this->newCompanyTrigger();
        $companyTags    = $this->createCompanyTags();
        $this->setCsrfHeader();
        $crawler        = $this->client->xmlHttpRequest('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId='.$companyTrigger->getId());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Modify Company Tags', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Add Company Tags', $this->client->getResponse()->getContent());

        // Get CSRF token from session
        $csrfToken = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('mautic.ajax.post')->getValue();

        $values  = [
            'companypointtriggerevent' => [
                'name'        => 'Event company tags one',
                'description' => 'Event company tags one',
                'properties'  => [
                    'add_tags'    => [$companyTags[0]->getId(), $companyTags[1]->getId()],
                    'remove_tags' => [],
                ],
                'type'      => 'companytags.updatetags',
                'triggerId' => $companyTrigger->getId(),
                '_token'    => $csrfToken,
            ],
        ];

        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('POST', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId='.$companyTrigger->getId(), $values);
        $content = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString($companyTags[0]->getTag(), $content['newContent']);
        $this->assertStringContainsString($companyTags[1]->getTag(), $content['newContent']);
    }

    public function testEditEventAddCompanyTag(): void
    {
        $companyTrigger = $this->newCompanyTrigger();
        $companyTags    = $this->createCompanyTags();
        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('GET', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId='.$companyTrigger->getId());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content    = \json_decode($this->client->getResponse()->getContent(), true);
        $contentNew = $content['newContent'];
        $html       = <<<HTML
$contentNew
HTML;
        $crawler = new Crawler($html);
        $token   = $crawler->filter('input[id=companypointtriggerevent__token]')->attr('value');
        $this->assertStringContainsString('Modify Company Tags', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Add Company Tags', $this->client->getResponse()->getContent());

        $name    = 'Event company tags one'.rand(1000, 99999);
        $values  = [
            'companypointtriggerevent' => [
                'name'        => $name,
                'description' => 'Event company tags one',
                'properties'  => [
                    'add_tags'    => [$companyTags[0]->getId(), $companyTags[1]->getId()],
                    'remove_tags' => [],
                ],
                'type'      => 'companytags.updatetags',
                'triggerId' => $companyTrigger->getId(),
                '_token'    => $token,
            ],
        ];

        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('POST', '/s/company/points/triggers/events/new?type=companytags.updatetags&tmpl=event&triggerId='.$companyTrigger->getId(), $values);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(1, $content['success']);
        $this->assertStringContainsString($name, $content['eventHtml']);
        $contentNew = $content['eventHtml'];
        $html       = <<<HTML
$contentNew
HTML;
        $crawler = new Crawler($html);

        $deletelink = $crawler->filter('a[data-menu-link=mautic_company_points_index]')->attr('href');
        $editLink   = $crawler->filter('a[data-toggle=ajaxmodal]')->attr('href');

        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('GET', $editLink);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content    = \json_decode($this->client->getResponse()->getContent(), true);
        $newContent = $content['newContent'];
        $html       = <<<HTML
$newContent
HTML;
        $crawler = new Crawler($html);
        $token   = $crawler->filter('input[id=companypointtriggerevent__token]')->attr('value');
        $name2   = 'Event company tags two'.rand(1000, 99999);
        $values  = [
            'companypointtriggerevent' => [
                'name'        => $name2,
                'description' => 'Event company tags two',
                'properties'  => [
                    'add_tags'    => [$companyTags[0]->getId(), $companyTags[1]->getId()],
                    'remove_tags' => [],
                ],
                'type'      => 'companytags.updatetags',
                'triggerId' => $companyTrigger->getId(),
                '_token'    => $token,
            ],
        ];
        $link = $crawler->filter('form[name=companypointtriggerevent]')->attr('action');
        $this->setCsrfHeader();
        $this->client->xmlHttpRequest('POST', $link, $values);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(1, $content['success']);
        $this->assertStringContainsString($name2, $content['eventHtml']);
    }

    private function newCompanyTrigger(string $name='Test Trigger', string $desc='Test Description', int $points=10, string $color='aaaccc'): CompanyTrigger
    {
        $email                    = $this->createEmail('Test Email', 'Test Email Description');
        $companyPointEventTrigger = $this->createCompanyPointTriggerEvent($email, 'Test Event Trigger', 'Test Event Trigger Description');
        $companyPointTrigger      = $this->createCompanyPointTrigger();
        $companyPointEventTrigger->setTrigger($companyPointTrigger);
        $this->em->persist($companyPointEventTrigger);
        $this->em->flush();

        return $companyPointTrigger;
    }

    private function createCompanyTags(): array
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

    private function createEmail(string $name, string $subject): Email
    {
        $emailModel = self::getContainer()->get('mautic.email.model.email');
        $email      = new Email();
        $email->setName($name);
        $email->setSubject($subject);
        $email->setIsPublished(true);
        assert($emailModel instanceof \Mautic\EmailBundle\Model\EmailModel);
        $emailModel->saveEntity($email);

        return $email;
    }

    private function createCompanyPointTriggerEvent(Email $email, string $name, string $description): \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent
    {
        $companyTriggerEventModel = self::getContainer()->get('mautic.companypoint.model.triggerevent');
        assert($companyTriggerEventModel instanceof \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel);
        $companyTriggerEvent = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent();
        $companyTriggerEvent->setName($name);
        $companyTriggerEvent->setDescription($description);
        $properties = [
            'email_to_owner' => true,
            'to'             => '',
            'cc'             => '',
            'email'          => $email->getId(),
        ];
        $companyTriggerEvent->setProperties($properties);
        $companyTriggerEvent->setType('companytags.sendemails');

        return $companyTriggerEvent;
    }

    private function createCompanyPointTrigger(string $name = 'Test Trigger', string $description = 'Test Description', int $points = 10, string $color = 'aaaccc'): CompanyTrigger
    {
        $companyTrigger = new CompanyTrigger();
        $companyTrigger->setName($name);
        $companyTrigger->setDescription($description);
        $companyTrigger->setPoints($points);
        $companyTrigger->setColor($color);
        $companyTrigger->setIsPublished(true);
        $this->em->persist($companyTrigger);
        $this->em->flush();

        return $companyTrigger;
    }
}

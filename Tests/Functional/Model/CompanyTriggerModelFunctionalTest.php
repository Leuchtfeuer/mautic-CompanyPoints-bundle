<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Company;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;

class CompanyTriggerModelFunctionalTest extends MauticMysqlTestCase
{
    private CompanyTriggerModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->model = self::getContainer()->get(CompanyTriggerModel::class);
    }

    public function testGetRepository(): void
    {
        $repo = $this->model->getRepository();
        $this->assertNotNull($repo);
    }

    public function testGetEventRepository(): void
    {
        $repo = $this->model->getEventRepository();
        $this->assertNotNull($repo);
    }

    public function testGetEventTriggerLogRepository()
    {
        $repo = $this->model->getEventTriggerLogRepository();
        $this->assertNotNull($repo);
    }

    public function testGetPermissionBase(): void
    {
        $this->assertEquals('companypoint:triggers', $this->model->getPermissionBase());
    }

    public function testGetEntityReturnsNew(): void
    {
        $entity = $this->model->getEntity();
        $this->assertNotNull($entity);
    }

    public function testGetEvents(): void
    {
        $events = $this->model->getEvents();
        $this->assertIsArray($events);
    }

    public function testGetEventGroups(): void
    {
        $groups = $this->model->getEventGroups();
        $this->assertIsArray($groups);
    }

    public function testSendEmailsReturnsArray(): void
    {
        $company = new Company();
        $user    = new User();
        $user->setEmail('test@example.com');
        $result = $this->model->sendEmails([$user], ['email' => 1], null, $company);
        $this->assertIsArray($result);
    }

    public function testDispatchEventReturnsNullOnInvalidEntity(): void
    {
        $entity = new CompanyTrigger();
        $result = $this->model->dispatchEvent('pre_save', $entity);
        $this->assertNull($result);
    }

    public function testGetColorForLeadPointsReturnsString(): void
    {
        $color = $this->model->getColorForLeadPoints(10);
        $this->assertIsString($color);
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

        //        $companyTriggerEventModel->saveEntity($companyTriggerEvent);
        return $companyTriggerEvent;
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

    private function createCompanyPointTrigger(string $name = 'Test Trigger', string $description = 'Test Description', int $points = 10, string $color = 'aaaccc'): CompanyTrigger
    {
        $companyTrigger = new CompanyTrigger();
        $companyTrigger->setName('Test Trigger');
        $companyTrigger->setDescription('Test Description');
        $companyTrigger->setPoints(10);
        $companyTrigger->setColor('aaaccc');
        $companyTrigger->setIsPublished(true);
        $this->em->persist($companyTrigger);
        $this->em->flush();

        return $companyTrigger;
    }

    // Add more tests for other public methods as needed
}

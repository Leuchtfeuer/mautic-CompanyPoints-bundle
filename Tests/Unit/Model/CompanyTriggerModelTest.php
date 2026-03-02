<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Unit\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CompanyTriggerModelTest extends TestCase
{
    private function getModel()
    {
        $companyTriggerRepository = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['saveEntity'])
            ->getMock();

        $companyTriggerRepository->method('saveEntity')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($companyTriggerRepository);

        return new CompanyTriggerModel(
            $this->createMock(IpLookupHelper::class),
            $this->createMock(LeadModel::class),
            $this->createMock(CompanyTriggerEventModel::class),
            $this->createMock(ContactTracker::class),
            $em,
            $this->createMock(CorePermissions::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Translator::class),
            $this->createMock(UserHelper::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CoreParametersHelper::class),
            $this->createMock(EmailModel::class),
            $this->createMock(MailHelper::class),
            $this->createMock(CompanyTagModel::class),
            $this->createMock(CompanySegmentModel::class)
        );
    }

    public function testGetRepository(): void
    {
        $model = $this->getModel();
        $this->assertNotNull($model->getRepository());
    }

    public function testGetEventRepository(): void
    {
        $model = $this->getModel();
        $this->assertNotNull($model->getEventRepository());
    }

    public function testGetEventTriggerLogRepository(): void
    {
        $model = $this->getModel();
        $this->assertNotNull($model->getEventTriggerLogRepository());
    }

    public function testGetPermissionBase(): void
    {
        $model = $this->getModel();
        $this->assertEquals('companypoint:triggers', $model->getPermissionBase());
    }

    public function testCreateFormThrowsExceptionOnInvalidEntity(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $model       = $this->getModel();
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $model->createForm(new \stdClass(), $formFactory);
    }

    public function testCreateFormReturnsForm(): void
    {
        $model       = $this->getModel();
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form        = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formFactory->method('create')->willReturn($form);
        $entity = $this->createMock(CompanyTrigger::class);
        $this->assertSame($form, $model->createForm($entity, $formFactory));
    }

    public function testSaveEntity(): void
    {
        $model  = $this->getModel();
        $entity = $this->createMock(CompanyTrigger::class);
        $entity->method('getId')->willReturn(null);
        $entity->method('isPublished')->willReturn(false);
        $model->saveEntity($entity);
        $this->assertTrue(true); // No exception means pass
    }

    public function testGetEntityReturnsNewIfNull(): void
    {
        $model = $this->getModel();
        $this->assertInstanceOf(CompanyTrigger::class, $model->getEntity());
    }

    public function testDispatchEventThrowsExceptionOnInvalidEntity(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $model  = $this->getModel();
        $entity = new \stdClass(); // Assign to variable
        $model->dispatchEvent('pre_save', $entity);
    }

    public function testSetEvents()
    {
        $model  = $this->getModel();
        $entity = $this->createMock(CompanyTrigger::class);
        $entity->method('getEvents')->willReturn([]);
        $entity->method('getId')->willReturn(null);
        $model->setEvents($entity, []);
        $this->assertTrue(true);
    }

    public function testGetEvents(): void
    {
        $model = $this->getModel();
        $this->assertIsArray($model->getEvents());
    }

    public function testGetEventGroups(): void
    {
        $model = $this->getModel();
        $this->assertIsArray($model->getEventGroups());
    }

    public function testTriggerEventReturnsFalseIfNotAnonymous(): void
    {
        $model    = $this->getModel();
        $security = $this->createMock(CorePermissions::class);
        $security->method('isAnonymous')->willReturn(false);
        $reflection = new \ReflectionProperty($model, 'security');
        $reflection->setAccessible(true);
        $reflection->setValue($model, $security);
        $this->assertFalse($model->triggerEvent(['id' => 1, 'type' => 'test'], $this->createMock(Lead::class)));
    }

    public function testTriggerEvents(): void
    {
        $originalModel = $this->getModel();
        $lead          = $this->createMock(Lead::class);
        $lead->method('getPoints')->willReturn(0);

        // Mock Collection for getGroupScores
        $collection = $this->createMock(\Doctrine\Common\Collections\Collection::class);
        $lead->method('getGroupScores')->willReturn($collection);

        // Mock repository with required methods
        $repo = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getPublishedByPointTotal', 'getPublishedByGroupScore', 'getLeadTriggeredEvents', 'find', 'saveEntities', 'detachEntities', 'deleteEntity'])
            ->getMock();
        $repo->method('getPublishedByPointTotal')->willReturn([]);
        $repo->method('getPublishedByGroupScore')->willReturn([]);
        $repo->method('getLeadTriggeredEvents')->willReturn([]);

        // Create a mock of CompanyTriggerModel with getEventRepository overridden
        $model = $this->getMockBuilder(get_class($originalModel))
            ->setConstructorArgs([
                $this->createMock(IpLookupHelper::class),
                $this->createMock(LeadModel::class),
                $this->createMock(CompanyTriggerEventModel::class),
                $this->createMock(ContactTracker::class),
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(CorePermissions::class),
                $this->createMock(EventDispatcherInterface::class),
                $this->createMock(UrlGeneratorInterface::class),
                $this->createMock(Translator::class),
                $this->createMock(UserHelper::class),
                $this->createMock(LoggerInterface::class),
                $this->createMock(CoreParametersHelper::class),
                $this->createMock(EmailModel::class),
                $this->createMock(MailHelper::class),
                $this->createMock(CompanyTagModel::class),
                $this->createMock(CompanySegmentModel::class),
            ])
            ->onlyMethods(['getEventRepository'])
            ->getMock();

        $model->method('getEventRepository')->willReturn($repo);

        $model->triggerEvents($lead);
        $this->assertTrue(true);
    }

    public function testGetColorForLeadPoints(): void
    {
        $model      = $this->getModel();
        $reflection = new \ReflectionProperty($model, 'triggers');
        $reflection->setAccessible(true);
        $reflection->setValue($model, [['points' => 10, 'color' => 'red']]);
        $this->assertEquals('red', $model->getColorForLeadPoints(10));
    }

    public function testSaveLog(): void
    {
        $originalModel = $this->getModel();
        $company       = $this->createMock(Company::class);
        $event         = $this->createMock(CompanyTriggerEvent::class);

        // Mock the repository and its saveEntity method
        $logRepo = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['saveEntity'])
            ->getMock();
        $logRepo->expects($this->once())->method('saveEntity');

        // Create a mock of CompanyTriggerModel with constructor args and override getEventTriggerLogRepository
        $model = $this->getMockBuilder(get_class($originalModel))
            ->setConstructorArgs([
                $this->createMock(IpLookupHelper::class),
                $this->createMock(LeadModel::class),
                $this->createMock(CompanyTriggerEventModel::class),
                $this->createMock(ContactTracker::class),
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(CorePermissions::class),
                $this->createMock(EventDispatcherInterface::class),
                $this->createMock(UrlGeneratorInterface::class),
                $this->createMock(Translator::class),
                $this->createMock(UserHelper::class),
                $this->createMock(LoggerInterface::class),
                $this->createMock(CoreParametersHelper::class),
                $this->createMock(EmailModel::class),
                $this->createMock(MailHelper::class),
                $this->createMock(CompanyTagModel::class),
                $this->createMock(CompanySegmentModel::class),
            ])
            ->onlyMethods(['getEventTriggerLogRepository'])
            ->getMock();

        $model->method('getEventTriggerLogRepository')->willReturn($logRepo);

        $model->saveLog($company, $event);
        $this->assertTrue(true);
    }

    public function testSendEmailsReturnsEmptyIfNoUsersOrEmail(): void
    {
        $model = $this->getModel();
        $this->assertEquals([], $model->sendEmails([], [], null, $this->createMock(Company::class)));
    }

    public function testSendEmailReturnsFalseIfEmailNotFound(): void
    {
        $model   = $this->getModel();
        $user    = $this->createMock(User::class);
        $company = $this->createMock(Company::class);
        $this->assertFalse($model->sendEmail(1, $user, null, $company));
    }

    public function testSendEmailFillsMailHelperCorrectly()
    {
        $email = $this->createMock(\Mautic\EmailBundle\Entity\Email::class);
        $email->method('isPublished')->willReturn(true);

        // Mock the actual EmailRepository class
        $emailRepo = $this->getMockBuilder(\Mautic\EmailBundle\Entity\EmailRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
        $emailRepo->method('find')->willReturn($email);

        $emailModel = $this->createMock(EmailModel::class);
        $emailModel->method('getRepository')->willReturn($emailRepo);

        $mailHelper = $this->getMockBuilder(MailHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setEmail', 'addTo', 'addCc', 'addBcc', 'getTokens', 'addTokens', 'send', 'reset'])
            ->getMock();

        $mailHelper->expects($this->once())->method('setEmail')->with($email);
        $mailHelper->expects($this->once())->method('addTo')->with('user@example.com');
        $mailHelper->method('getTokens')->willReturn([]);
        $mailHelper->expects($this->atLeastOnce())->method('addTokens');
        $mailHelper->method('send')->willReturn(true);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');

        $company = $this->createMock(Company::class);
        $company->method('getFields')->willReturn([
            'professional' => [],
            'personal'     => [],
            'custom'       => [],
            'core'         => [],
        ]);
        $company->method('getScore')->willReturn(0);

        // Mock CompanyTagModel
        $companyTagModel = $this->createMock(CompanyTagModel::class);
        $companyTagModel->method('getTagsByCompany')->willReturn([]);

        // Mock CompanySegmentModel and its repository
        $companySegmentRepo = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['findBy'])
            ->getMock();

        // Use the correct repository class for the mock
        $companySegmentRepo = $this->getMockBuilder(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegmentsRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy'])
            ->getMock();
        $companySegmentRepo->method('findBy')->willReturn([]);

        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companySegmentModel->method('getCompaniesSegmentsRepository')->willReturn($companySegmentRepo);

        $model = new CompanyTriggerModel(
            $this->createMock(IpLookupHelper::class),
            $this->createMock(LeadModel::class),
            $this->createMock(CompanyTriggerEventModel::class),
            $this->createMock(ContactTracker::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(CorePermissions::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Translator::class),
            $this->createMock(UserHelper::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CoreParametersHelper::class),
            $emailModel,
            $mailHelper,
            $companyTagModel,
            $companySegmentModel
        );

        $result = $model->sendEmail(1, $user, null, $company);
        $this->assertTrue($result);
    }
}

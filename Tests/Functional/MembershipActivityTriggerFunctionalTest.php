<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use PHPUnit\Framework\Assert;

class MembershipActivityTriggerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FunctionalFixtureHelper $fixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
    }

    public function activityEmulationDataProvider(): array
    {
        return [
            'email link clicked'              => ['email_link_clicked'],
            'page visit'                      => ['page_visit'],
            'form submit'                     => ['form_submit'],
            'form submit with tracking'       => ['form_submit_with_tracking'],
        ];
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testActivityOfKnownContactSuccessPath(string $emulationMethod): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on contact click',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();

        // Create a contact and associate the company
        $contact = new Lead();
        $contact->setEmail('jj@example.com');
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        // 2. Emulate the activity using the method from the data provider
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($contact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($contact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($contact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($contact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check if the company has the tag assigned
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should have one tag after the link click.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstEverActivityIsNotTriggeredWhenActiveMembersExist(string $emulationMethod): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('First Ever Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on first ever contact activity',
            CompanyTrigger::ACTIVITY_FIRST_EVER
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add First Ever tag action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Active Company Inc.');
        $this->em->flush();

        // Create a pre-existing active contact for the company
        $activeContact = new Lead();
        $activeContact->setEmail('already.active@example.com');
        $activeContact->setLastActive((new \DateTime())->modify('-2 days')); // Set last active in the past
        $activeContact->setCompany($company->getName());
        $this->em->persist($activeContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($activeContact, $company, (new \DateTime())->modify('-10 days'));
        // Create a new contact who will perform the action
        $newContact = new Lead();
        $newContact->setEmail('new.contact@example.com');
        $newContact->setCompany($company->getName());
        $this->em->persist($newContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($newContact, $company);

        // 2. Emulate the activity using the method from the data provider
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($newContact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($newContact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($newContact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company was NOT tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(0, $tags, 'Company should NOT be tagged as there was already an active member.');
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstEverActivityIsTriggeredWhenOtherMemberIsInactive(string $emulationMethod): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('First Ever Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on first ever contact activity',
            CompanyTrigger::ACTIVITY_FIRST_EVER
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add First Ever tag action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Active Company Inc.');
        $this->em->flush();

        // Create a pre-existing but inactive contact for the company
        $inactiveContact = new Lead();
        $inactiveContact->setEmail('already.inactive@example.com');
        $inactiveContact->setCompany($company->getName());
        $this->em->persist($inactiveContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($inactiveContact, $company, (new \DateTime())->modify('-10 days'));

        // Create a new contact who will perform the action
        $newContact = new Lead();
        $newContact->setEmail('new.contact@example.com');
        $newContact->setCompany($company->getName());
        $this->em->persist($newContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($newContact, $company);

        // 2. Emulate the activity using the method from the data provider
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($newContact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($newContact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($newContact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company WAS tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should be tagged as the other member was inactive.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "first ever" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstActivityWithin30DaysIsTriggeredWhenLastActivityWasLongAgo(string $emulationMethod): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('First in 30 Days Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on first activity in 30 days',
            CompanyTrigger::ACTIVITY_FIRST_WITHIN_30_DAYS
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add First in 30 Days Tag action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Old Activity Inc.');
        $this->em->flush();

        // Create a pre-existing contact whose last activity was > 30 days ago
        $oldContact = new Lead();
        $oldContact->setEmail('old.contact@example.com');
        $oldContact->setLastActive((new \DateTime())->modify('-35 days')); // Last active was 35 days ago
        $oldContact->setCompany($company->getName());
        $this->em->persist($oldContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($oldContact, $company, (new \DateTime())->modify('-40 days'));

        // Create a new contact who will perform the current action
        $newContact = new Lead();
        $newContact->setEmail('new.contact@example.com');
        $newContact->setCompany($company->getName());
        $this->em->persist($newContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($newContact, $company);

        // 2. Emulate the activity which should trigger the action
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($newContact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($newContact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($newContact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company WAS tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should be tagged as this is the first activity in 30 days.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "first in 30 days" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstActivityWithin30DaysIsNotTriggeredWhenRecentActivityExists(string $emulationMethod): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('Should Not Be Added Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on first activity in 30 days',
            CompanyTrigger::ACTIVITY_FIRST_WITHIN_30_DAYS
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Should not run action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Recent Activity Inc.');
        $this->em->flush();

        // Create a pre-existing contact who was active recently (e.g., 15 days ago)
        $recentContact = new Lead();
        $recentContact->setEmail('recent.contact@example.com');
        $recentContact->setLastActive((new \DateTime())->modify('-15 days')); // This is within the 30-day window
        $recentContact->setCompany($company->getName());
        $this->em->persist($recentContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($recentContact, $company, (new \DateTime())->modify('-20 days'));

        // Create another contact who will perform the current action
        $newContact = new Lead();
        $newContact->setEmail('new.contact@example.com');
        $newContact->setCompany($company->getName());
        $this->em->persist($newContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($newContact, $company);

        // 2. Emulate the activity, which should NOT trigger the action
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($newContact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($newContact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($newContact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company was NOT tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(0, $tags, 'Company should NOT be tagged as there was recent activity within the last 30 days.');
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstActivityOfNewContactTriggersSuccessfully(string $emulationMethod): void
    {
        // 1. Setup: Create entities and configure the trigger
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('New Contact Activity Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag on new contact first activity',
            CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add New Contact Activity Tag Action',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Newbie Inc.');
        $this->em->flush();

        // Create a pre-existing contact to ensure their presence doesn't interfere.
        // This contact is not "new" in the company.
        $oldContact = new Lead();
        $oldContact->setEmail('old.timer@example.com');
        $oldContact->setCompany($company->getName());
        $this->em->persist($oldContact);
        $this->em->flush();
        // Add this contact as if they joined 10 days ago
        $this->fixtureHelper->addContactToCompany($oldContact, $company, (new \DateTime())->modify('-10 days'));

        // Create the "new" contact who will perform the action
        $newContact = new Lead();
        $newContact->setEmail('new.kid@example.com');
        $newContact->setCompany($company->getName());
        $this->em->persist($newContact);
        $this->em->flush();
        // Add this contact to the company now, making them a "new contact"
        $this->fixtureHelper->addContactToCompany($newContact, $company);
        $this->em->flush();

        // 2. Action: Emulate the first activity for the new contact
        match ($emulationMethod) {
            'email_link_clicked'        => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit'                => $this->fixtureHelper->emulatePageVisit($newContact),
            'form_submit'               => $this->fixtureHelper->emulateFormSubmit($newContact),
            'form_submit_with_tracking' => $this->fixtureHelper->emulateFormSubmitWithTracking($newContact),
            default                     => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Assertion: Check that the company was tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should be tagged on the first activity of a new contact.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "new contact activity" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    public function testFirstActivityOfNewContactDoesNotTriggerForOldContact(): void
    {
        // 1. Setup: Create entities and configure the trigger for a "new" contact
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('Should Not Be Added');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag on new contact first activity',
            CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'This action should not run',
            [$companyTag->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Established Inc.');
        $this->em->flush();

        // Create an "old" contact for the company
        $oldContact = new Lead();
        $oldContact->setEmail('old.hand@example.com');
        $oldContact->setCompany($company->getName());
        $oldContact->setLastActive((new \DateTime())->modify('-10 days'));
        $this->em->persist($oldContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($oldContact, $company, (new \DateTime())->modify('-20 days'));
        $this->em->flush();

        // 2. Action: Emulate an activity for this EXISTING contact
        $this->fixtureHelper->emulateEmailLinkClicked($oldContact);

        // 3. Assertion: Check that the company was NOT tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(0, $tags, 'Company should NOT be tagged as the activity was from an existing contact, not a new one.');
    }

    public function testTriggerIsNotExecutedTwiceOnNextActivity(): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $email        = $this->fixtureHelper->createEmail('Test Email', 'A test email');
        $contactEmail = 'admin@example.com';

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Email on contact activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );

        $this->fixtureHelper->createCompanyEmailAction(
            $trigger,
            $email,
            'Send email action',
            $contactEmail
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Acme Corp');
        $this->em->flush();

        // Create a contact and associate the company
        $contact = new Lead();
        $contact->setEmail('john.doe@test.com');
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        // 2. Emulate the first activity and check for one email
        $this->fixtureHelper->emulatePageVisit($contact);

        $messages = $this->getMailerMessagesByToAddress($contactEmail);
        Assert::assertCount(1, $messages, 'One email should have been sent after the first activity.');

        // 3. Emulate the second activity and check that no new email was sent
        $this->fixtureHelper->emulatePageVisit($contact);

        $messagesAfterSecondActivity = $this->getMailerMessagesByToAddress($contactEmail);
        Assert::assertCount(
            1,
            $messagesAfterSecondActivity,
            'No new email should be sent after the second activity; the total should remain one.'
        );
    }

    public function testNewContactAndCompanyFromForm(): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('First Ever Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on first ever contact activity',
            CompanyTrigger::ACTIVITY_FIRST_EVER
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add First Ever tag action',
            [$companyTag->getId()]
        );

        $form = $this->fixtureHelper->createFormWithCompanyViaApi('Test Form');
        $this->fixtureHelper->submitForm($form, [
            'mauticform[email]'   => 'test@example.com',
            'mauticform[company]' => 'Test Company',
        ]);

        // 3. Check that the company was NOT tagged
        $this->em->clear();

        $company = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company']);
        Assert::assertNotNull($company);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);
        Assert::assertCount(1, $tags, 'Company should be tagged as this was the first ever member activity.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "first ever" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    public function testActivityOfAnonymousContact(): void
    {
        // 1. Create all required entities
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');
        $trigger    = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on contact click',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );
        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getId()]
        );

        $companyTag2 = $this->fixtureHelper->createCompanyTag('This tag should not be added');
        $trigger2    = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on contact click',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );
        $this->fixtureHelper->createCompanyTagsAction(
            $trigger2,
            'Add Test Tag action',
            [$companyTag2->getId()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();

        // Create a contact and associate the company
        $contact = new Lead();
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company, (new \DateTime())->modify('-1 day'));
        $this->em->flush();

        // 2. Emulate the activity
        $this->fixtureHelper->emulatePageVisit($contact);

        // 3. Check if the company has the tag assigned
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $tags = $tagsRepostory->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should have one tag after the link click.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    public function testEventsTriggeredOnlyForPrimaryCompany(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();

        $companyTag = $this->fixtureHelper->createCompanyTag('Primary Check Tag');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on known contact activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Tag action',
            [$companyTag->getId()]
        );

        $primaryCompany   = $this->fixtureHelper->createCompany('Primary Company Inc.');
        $secondaryCompany = $this->fixtureHelper->createCompany('Secondary Company Ltd.');
        $this->em->flush();

        $contact = new Lead();
        $contact->setEmail('multicompany@example.com');
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $this->em->persist($contact);
        $this->em->flush();

        // Associate to the Primary Company (isPrimary = true)
        $this->fixtureHelper->addContactToCompany($contact, $primaryCompany, null, true);

        // Associate to the Secondary Company (isPrimary = false)
        $this->fixtureHelper->addContactToCompany($contact, $secondaryCompany, null, false);
        $this->em->flush();

        $this->fixtureHelper->emulatePageVisit($contact);

        $this->em->clear();

        // Assert Primary Company HAS the tag
        /** @var Company|null $reloadedPrimary */
        $reloadedPrimary = $this->em->getRepository(Company::class)->find($primaryCompany->getId());
        Assert::assertNotNull($reloadedPrimary);

        $tagsRepostory = $this->em->getRepository(CompanyTags::class);
        $this->assertInstanceOf(CompanyTagsRepository::class, $tagsRepostory);
        $primaryTags = $tagsRepostory->getTagsByCompany($reloadedPrimary);

        Assert::assertCount(1, $primaryTags, 'Primary company SHOULD be tagged.');
        Assert::assertSame($companyTag->getId(), $primaryTags[0]->getId());

        // Assert Secondary Company does NOT have the tag
        /** @var Company|null $reloadedSecondary */
        $reloadedSecondary = $this->em->getRepository(Company::class)->find($secondaryCompany->getId());
        Assert::assertNotNull($reloadedSecondary);
        $secondaryTags = $tagsRepostory->getTagsByCompany($reloadedSecondary);

        Assert::assertCount(0, $secondaryTags, 'Secondary company SHOULD NOT be tagged.');
    }

    public function testCompanyFulfillsCompanySegmentFilter(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $segment = $this->fixtureHelper->createCompanySegment('abc');
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();
        $this->fixtureHelper->addCompanyToSegment($company, $segment);
        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on contact click',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT,
            ['operator' => 'in', 'segments' => [$segment->getId()]]
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getId()]
        );

        $contact =$this->fixtureHelper->createContact('jj@example.com');
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->fixtureHelper->emulateEmailLinkClicked($contact);
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        /** @var CompanyTagsRepository $tagsRepository */
        $tagsRepository = $this->em->getRepository(CompanyTags::class);
        $tags           = $tagsRepository->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should have one tag after the link click.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    public function testCompanyDoesntFulfillsCompanySegmentFilter(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $segment = $this->fixtureHelper->createCompanySegment('abc');
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();
        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag company on contact click',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT,
            ['operator' => 'in', 'segments' => [$segment->getId()]]
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getId()]
        );

        $contact =$this->fixtureHelper->createContact('jj@example.com');
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->fixtureHelper->emulateEmailLinkClicked($contact);
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        /** @var CompanyTagsRepository $tagsRepository */
        $tagsRepository = $this->em->getRepository(CompanyTags::class);
        $tags           = $tagsRepository->getTagsByCompany($company);

        Assert::assertCount(0, $tags, 'Company should not have a tag after link click.');
    }

    public function testModifyCompanySegmentsTriggerActionAdd(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();

        $targetSegment = $this->fixtureHelper->createCompanySegment('Target Segment', 'target-segment');
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Modify segments on contact activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );

        $this->fixtureHelper->createCompanySegmentsAction(
            $trigger,
            'Add to target segment',
            [$targetSegment],
            []
        );

        $contact = $this->fixtureHelper->createContact('test@example.com');
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->fixtureHelper->emulatePageVisit($contact);
        $this->em->clear();

        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $companiesSegments = $this->em->getRepository(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments::class)
            ->findBy(['company' => $updatedCompany, 'companySegment' => $targetSegment]);

        Assert::assertCount(1, $companiesSegments);
        Assert::assertSame($targetSegment->getId(), $companiesSegments[0]->getCompanySegment()->getId());
    }

    public function testModifyCompanySegmentsTriggerActionRemove(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();

        $segmentToRemove = $this->fixtureHelper->createCompanySegment('Segment to Remove', 'segment-to-remove');
        $company = $this->fixtureHelper->createCompany('Test Inc.');
        $this->em->flush();

        $this->fixtureHelper->addCompanyToSegment($company, $segmentToRemove);

        $initialSegments = $this->em->getRepository(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments::class)
            ->findBy(['company' => $company, 'companySegment' => $segmentToRemove]);
        Assert::assertCount(1, $initialSegments);

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Remove segments on contact activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT
        );

        $this->fixtureHelper->createCompanySegmentsAction(
            $trigger,
            'Remove from segment',
            [],
            [$segmentToRemove]
        );

        $contact = $this->fixtureHelper->createContact('test@example.com');
        $contact->setLastActive((new \DateTime())->modify('-1 day'));
        $contact->setCompany($company->getName());
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->fixtureHelper->emulatePageVisit($contact);
        $this->em->clear();

        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $companiesSegments = $this->em->getRepository(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments::class)
            ->findBy(['company' => $updatedCompany, 'companySegment' => $segmentToRemove]);

        Assert::assertCount(0, $companiesSegments);
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
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
            'email link clicked' => ['email_link_clicked'],
            'page visit'       => ['page_visit'],
//            'form submit'       => ['form_submit'],
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
            [$companyTag->getTag()]
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
            'email_link_clicked' => $this->fixtureHelper->emulateEmailLinkClicked($contact),
            'page_visit' => $this->fixtureHelper->emulatePageVisit($contact),
            default => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check if the company has the tag assigned
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($company);

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
            [$companyTag->getTag()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('Active Company Inc.');
        $this->em->flush();

        // Create a pre-existing active contact for the company
        $activeContact = new Lead();
        $activeContact->setEmail('already.active@example.com');
        $activeContact->setLastActive((new \DateTime())->modify('-2 days')); // Set last active in the past
        $this->em->persist($activeContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($activeContact, $company, (new \DateTime())->modify('-10 days'));
        // Create a new contact who will perform the action
        $newContact = new Lead();
        $newContact->setEmail('new.contact@example.com');
        $this->em->persist($newContact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($newContact, $company);

        // 2. Emulate the activity using the method from the data provider
        match ($emulationMethod) {
            'email_link_clicked' => $this->fixtureHelper->emulateEmailLinkClicked($newContact),
            'page_visit' => $this->fixtureHelper->emulatePageVisit($newContact),
            default => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company was NOT tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($company);

        Assert::assertCount(0, $tags, 'Company should NOT be tagged as there was already an active member.');
    }

    /**
     * @dataProvider activityEmulationDataProvider
     */
    public function testFirstEverActivityIsTriggeredWhenNoActiveMembersExist(string $emulationMethod): void
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
            [$companyTag->getTag()]
        );

        // Create a company
        $company = $this->fixtureHelper->createCompany('New Company Inc.');
        $this->em->flush();

        // Create a new contact who will perform the first action. This contact has no lastActive date yet.
        $contact = new Lead();
        $contact->setEmail('first.contact@example.com');
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);

        // 2. Emulate the activity using the method from the data provider
        match ($emulationMethod) {
            'email_link_clicked' => $this->fixtureHelper->emulateEmailLinkClicked($contact),
            'page_visit' => $this->fixtureHelper->emulatePageVisit($contact),
            default => throw new \InvalidArgumentException("Unknown emulation type: $emulationMethod")
        };

        // 3. Check that the company WAS tagged
        $this->em->clear();

        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should be tagged as this was the first ever member activity.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "first ever" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
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
            [$companyTag->getTag()]
        );

        $form = $this->fixtureHelper->createFormWithCompanyViaApi('Test Form');
        $this->fixtureHelper->submitForm($form, [
            'mauticform[email]'   => 'test@example.com',
            'mauticform[company]' => 'Test Company',
        ]);

        // 3. Check that the company was NOT tagged
        $this->em->clear();

        /** @var Company|null $updatedCompany */
        $company = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company']);
        Assert::assertNotNull($company);

        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($company);
        Assert::assertCount(1, $tags, 'Company should be tagged as this was the first ever member activity.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct "first ever" tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

}
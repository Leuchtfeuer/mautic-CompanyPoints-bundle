<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CampaignBundle\Tests\Functional\Fixtures\FixtureHelper as CampaignFixtureHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeadsRepository;

class ModifyCampaignsActionHandlerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;
    private FunctionalFixtureHelper $fixtureHelper;
    private CampaignFixtureHelper $campaignFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper         = new FunctionalFixtureHelper($this->em, $this->client);
        $this->campaignFixtureHelper = new CampaignFixtureHelper($this->em);
    }

    /**
     * @param string[] $leadsToEndUpInCampaign
     *
     * @dataProvider triggerContactDataProvider
     */
    public function testAddLeadToCampaignTriggerAction(string $triggerContacts, array $leadsToEndUpInCampaign): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company    = $this->fixtureHelper->createCompany('abc');
        $contactIds = $this->createAllLeads($company);
        $campaign   = $this->campaignFixtureHelper->createCampaign('Add Lead To Campaign Company Trigger Action');
        $this->campaignFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag on new contact first activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );

        $triggerEvent = $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'This action should add contact to campaign',
            [$campaign],
            [],
            [],
            $triggerContacts,
        );

        $contactToEmulate = $this->em->getRepository(Lead::class)->find($contactIds['known-contact-with-most-recent-activity']);
        $this->assertInstanceOf(Lead::class, $contactToEmulate);
        $this->fixtureHelper->emulateEmailLinkClicked($contactToEmulate);

        $this->em->clear();

        $campaignId       = $campaign->getId();
        $reloadedCampaign = $this->em->getRepository(Campaign::class)->find($campaignId);
        $this->assertInstanceOf(Campaign::class, $reloadedCampaign);

        /** @var CampaignLead[] $campaignLeads */
        $campaignLeads = $reloadedCampaign->getLeads()->toArray();

        $actualLeadIds = array_map(
            fn ($campaignLead) => $campaignLead->getLead()->getId(),
            $campaignLeads
        );
        sort($actualLeadIds);

        $expectedLeadIds = array_map(fn ($id) => $contactIds[$id], $leadsToEndUpInCampaign);
        sort($expectedLeadIds);

        $this->assertEquals($expectedLeadIds, $actualLeadIds);
    }

    /**
     * @param string[] $leadsToBeRemovedFromCampaign
     *
     * @dataProvider triggerContactDataProvider
     */
    public function testRemoveLeadFromCampaignTriggerAction(string $triggerContacts, array $leadsToBeRemovedFromCampaign): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company    = $this->fixtureHelper->createCompany('abc');
        $contactIds = $this->createAllLeads($company);
        $campaign   = $this->campaignFixtureHelper->createCampaign('Remove Lead From Campaign Company Trigger Action');
        $this->campaignFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');

        foreach ($contactIds as $contactId) {
            $contact = $this->em->getRepository(Lead::class)->find($contactId);
            $this->assertInstanceOf(Lead::class, $contact);
            $this->campaignFixtureHelper->addContactToCampaign($contact, $campaign);
        }
        $this->em->flush();
        $this->em->clear();

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Remove contact from campaign trigger',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );

        $triggerEvent = $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'This action should remove contacts from campaign',
            [],
            [$campaign],
            [],
            $triggerContacts,
        );

        $contactToEmulate = $this->em->getRepository(Lead::class)->find($contactIds['known-contact-with-most-recent-activity']);
        $this->assertInstanceOf(Lead::class, $contactToEmulate);
        $this->fixtureHelper->emulateEmailLinkClicked($contactToEmulate);
        $this->em->clear();

        $campaignId               = $campaign->getId();
        $campaignMemberRepository = $this->em->getRepository(CampaignLead::class);
        $campaignMembers          = $campaignMemberRepository->findBy(['campaign' => $campaignId]);

        $expectedRemovedLeadIds = array_map(fn ($id) => $contactIds[$id], $leadsToBeRemovedFromCampaign);

        foreach ($campaignMembers as $campaignMember) {
            $leadId = $campaignMember->getLead()->getId();
            if (in_array($leadId, $expectedRemovedLeadIds, true)) {
                $this->assertTrue(
                    $campaignMember->getManuallyRemoved(),
                    "Lead {$leadId} should be marked as removed from campaign"
                );
            } else {
                $this->assertFalse(
                    $campaignMember->getManuallyRemoved(),
                    "Lead {$leadId} should NOT be marked as removed from campaign"
                );
            }
        }
    }

    public function testAddToOrRestartCampaignTriggerAction(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company    = $this->fixtureHelper->createCompany('abc');
        $contactIds = $this->createAllLeads($company);
        $campaign   = $this->campaignFixtureHelper->createCampaign('Remove Lead From Campaign Company Trigger Action');
        $campaign->setAllowRestart(true);
        $this->em->persist($campaign);
        $this->campaignFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');

        $contactsToAdd = [
            $contactIds['known-contact-with-most-recent-activity'],
            $contactIds['youngest-known-contact'],
        ];

        // Add two contacts to ensure that restart works
        foreach ($contactsToAdd as $contactId) {
            $contact = $this->em->getRepository(Lead::class)->find($contactId);
            $this->assertInstanceOf(Lead::class, $contact);
            $this->campaignFixtureHelper->addContactToCampaign($contact, $campaign);
        }

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Remove contact from campaign trigger',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );

        $triggerEvent = $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'This action should add to or restart campaign',
            [],
            [],
            [$campaign],
            'all_contacts',
        );

        $contactToEmulate = $this->em->getRepository(Lead::class)->find($contactIds['known-contact-with-most-recent-activity']);
        $this->assertInstanceOf(Lead::class, $contactToEmulate);
        $this->fixtureHelper->emulateEmailLinkClicked($contactToEmulate);
        $this->em->clear();

        $campaignId               = $campaign->getId();
        $campaignMemberRepository = $this->em->getRepository(CampaignLead::class);
        $campaignMembers          = $campaignMemberRepository->findBy(['campaign' => $campaignId]);

        $this->assertCount(count($contactIds), $campaignMembers);

        foreach ($campaignMembers as $campaignMember) {
            $this->assertTrue($campaignMember->getManuallyAdded(), 'name = '.$campaignMember->getLead()->getId().', manuallyRemoved = '.$campaignMember->getManuallyRemoved().', manuallyAdded = '.$campaignMember->getManuallyAdded());
            $this->assertFalse($campaignMember->getManuallyRemoved());
            // Contacts that were already in the campaign should now be in rotation 2
            if (in_array($campaignMember->getLead()->getId(), $contactsToAdd, true)) {
                $this->assertEquals(2, $campaignMember->getRotation());
            }
        }
    }

    public function testModifyCampaignActionForPlaceholderContact(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        // required for placeholder contacts to be created
        $this->fixtureHelper->createAndEnableCompanySegmentsPlugin();

        $company      = $this->fixtureHelper->createCompany('abc', 'a@a.com');
        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
        $this->assertInstanceOf(CompanyModel::class, $companyModel);
        $companyModel->saveEntity($company);

        $contactIds = $this->createAllLeads($company);
        $campaign   = $this->campaignFixtureHelper->createCampaign('Remove Lead From Campaign Company Trigger Action');
        $campaign->setAllowRestart(true);
        $this->em->persist($campaign);
        $this->campaignFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');
        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Remove contact from campaign trigger',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );

        $triggerEvent = $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'Add placeholder contact to campaign',
            [$campaign],
            [],
            [],
            'placeholder_contact',
        );

        $contactToEmulate = $this->em->getRepository(Lead::class)->find($contactIds['known-contact-with-most-recent-activity']);
        $this->assertInstanceOf(Lead::class, $contactToEmulate);
        $this->fixtureHelper->emulateEmailLinkClicked($contactToEmulate);
        $this->em->clear();

        $campaignId       = $campaign->getId();
        $reloadedCampaign = $this->em->getRepository(Campaign::class)->find($campaignId);
        $this->assertInstanceOf(Campaign::class, $reloadedCampaign);
        /** @var CampaignLead[] $campaignLeads */
        $campaignLeads = $reloadedCampaign->getLeads()->toArray();
        $this->assertCount(1, $campaignLeads);
        $campaignLead = $campaignLeads[0];
        $this->assertInstanceOf(CampaignLead::class, $campaignLead);

        $companyPlaceholderRepository = $this->em->getRepository(CompaniesPlaceholderLeads::class);
        $this->assertInstanceOf(CompaniesPlaceholderLeadsRepository::class, $companyPlaceholderRepository);
        $placeholderLead = $companyPlaceholderRepository->getPrimaryLeadOfCompany($company->getId());
        $this->assertInstanceOf(Lead::class, $placeholderLead);
        $this->assertEquals($placeholderLead->getId(), $campaignLead->getLead()->getId());
    }

    /**
     * @return array<string, int>
     */
    private function createAllLeads(Company $company): array
    {
        $defaultLastActiveDate = new \DateTime('-1 hour');
        $defaultDateAddedDate  = new \DateTime('-5 days');

        $contacts = [];
        $contact  = $this->createUnknownContact((new \DateTime())->modify('-1 hour'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['youngest-unknown-contact'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('youngestknowncontact@abc.com', (new \DateTime())->modify('-2 hours'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['youngest-known-contact'] = $contact->getId();

        // oldest unkown contact with non-recent activity
        $contact = $this->createUnknownContact((new \DateTime())->modify('-31 days'), (new \DateTime())->modify('-31 days'));
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['oldest-unknown-contact'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('oldestknowncontact@abc.com', (new \DateTime())->modify('-9 days'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['oldest-known-contact'] = $contact->getId();

        $contact = $this->createUnknownContact($defaultDateAddedDate, (new \DateTime())->modify('+30 minutes'));
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['contact-with-most-recent-activity'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('knowncontactwithmostrecentactivity@abc.com', $defaultDateAddedDate, new \DateTime());
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['known-contact-with-most-recent-activity'] = $contact->getId();

        return $contacts;
    }

    private function createUnknownContact(\DateTime $dateAdded, \DateTime $dateLastActive): Lead
    {
        $contact = new Lead();
        $contact->setDateAdded($dateAdded);
        $contact->setLastActive($dateLastActive);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    private function createContactWithDateAddedAndDateLastActive(string $email, \DateTime $dateAdded, \DateTime $dateLastActive): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);
        $contact->setDateAdded($dateAdded);
        $contact->setLastActive($dateLastActive);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    /**
     * @return array<string, array{string, string[]}>
     */
    public function triggerContactDataProvider(): array
    {
        return [
            'youngest_contact'                        => ['youngest_contact', ['youngest-unknown-contact']],
            'youngest_known_contact'                  => ['youngest_known_contact', ['youngest-known-contact']],
            'oldest_contact'                          => ['oldest_contact', ['oldest-unknown-contact']],
            'oldest_known_contact'                    => ['oldest_known_contact', ['oldest-known-contact']],
            'contact_with_most_recent_activity'       => ['contact_with_most_recent_activity', ['contact-with-most-recent-activity']],
            'known_contact_with_most_recent_activity' => ['known_contact_with_most_recent_activity', ['known-contact-with-most-recent-activity']],
            'all_contacts_with_recent_activity'       => [
                'all_contacts_with_recent_activity',
                ['youngest-unknown-contact', 'youngest-known-contact', 'oldest-known-contact', 'contact-with-most-recent-activity', 'known-contact-with-most-recent-activity'],
            ],
            'all_known_contacts_with_recent_activity' => ['all_known_contacts_with_recent_activity', ['known-contact-with-most-recent-activity', 'youngest-known-contact', 'oldest-known-contact']],
            'all_contacts'                            => [
                'all_contacts',
                ['youngest-unknown-contact', 'youngest-known-contact', 'oldest-unknown-contact', 'oldest-known-contact', 'contact-with-most-recent-activity', 'known-contact-with-most-recent-activity'],
            ],
            'all_known_contacts' => [
                'all_known_contacts',
                ['youngest-known-contact', 'oldest-known-contact', 'known-contact-with-most-recent-activity'],
            ],
        ];
    }
}

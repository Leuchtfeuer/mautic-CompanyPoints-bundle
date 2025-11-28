<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use Mautic\CampaignBundle\Tests\Functional\Fixtures\FixtureHelper as CampaignFixtureHelper;

class PointTriggerSubscriberFunctionalTest extends MauticMysqlTestCase
{
    private FunctionalFixtureHelper $fixtureHelper;
    private CampaignFixtureHelper $campaginFixtureHelper;
    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->campaginFixtureHelper = new CampaignFixtureHelper($this->em);
    }

    /**
     * @dataProvider triggerContactDataProvider
     */
    public function testAddLeadToCampaignTriggerAction(string $triggerContacts, $leadsToEndUpInCampaign): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company = $this->fixtureHelper->createCompany("abc");
        $contactIds = $this->createAllLeads($company);
        $campaign = $this->campaginFixtureHelper->createCampaign('Add Lead To Campaign Company Trigger Action');
        $this->campaginFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag on new contact first activity',
            CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT
        );

        $triggerEvent = $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'This action should not run',
            [$campaign],
            [],
            $triggerContacts,
        );

        $contactToEmulate = $this->em->getRepository(Lead::class)->find($contactIds['known-contact-with-most-recent-activity']);

        // 2. Action: Emulate an activity for this EXISTING contact
        $this->fixtureHelper->emulateEmailLinkClicked($contactToEmulate);

        // 3. Assertion: Check that the company was NOT tagged
        $this->em->clear();

        $campaignId = $campaign->getId();
        $reloadedCampaign = $this->em->getRepository(Campaign::class)->find($campaignId);

        $campaignLeads = $reloadedCampaign->getLeads()->toArray();

        $actualLeadIds = array_map(
            fn($campaignLead) => $campaignLead->getLead()->getId(),
            $campaignLeads
        );
        sort($actualLeadIds);

        $identifiers = is_array($leadsToEndUpInCampaign) ? $leadsToEndUpInCampaign : [$leadsToEndUpInCampaign];
        $expectedLeadIds = array_map(fn($id) => $contactIds[$id], $identifiers);
        sort($expectedLeadIds);

        $this->assertEquals($expectedLeadIds, $actualLeadIds);
    }

    private function createAllLeads(Company $company): array
    {
        $defaultLastActiveDate = new \DateTime('-1 hour');
        $defaultDateAddedDate = new \DateTime('-5 days');

        $contacts = [];
        $contact = $this->createUnknownContact((new \DateTime())->modify('-1 hour'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['youngest-unknown-contact'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('youngestknowncontact@abc.com', (new \DateTime())->modify('-2 hours'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['youngest-known-contact'] = $contact->getId();

        //oldest unkown contact with non-recent activity
        $contact = $this->createUnknownContact((new \DateTime())->modify('-31 days'), (new \DateTime())->modify('-31 days'));
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['oldest-unknown-contact'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('oldestknowncontact@abc.com', (new \DateTime())->modify('-9 days'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['oldest-known-contact'] = $contact->getId();

        $contact = $this->createUnknownContact($defaultDateAddedDate, (new \DateTime())->modify('+30 minutes'));
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['contact-with-most-recent-activity'] = $contact->getId();

        $contact = $this->createContactWithDateAddedAndDateLastActive('knowncontactwithmostrecentactivity@abc.com', $defaultDateAddedDate, (new \DateTime()));
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $contacts['known-contact-with-most-recent-activity'] = $contact->getId();

        return $contacts;
    }

    private function createUnknownContact(\DateTime $dateAdded, \DateTime $dateLastActive): Lead
    {
        $contact = new Lead();
        // Keine E-Mail setzen - bleibt anonymous/unknown
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

    public function triggerContactDataProvider(): array
    {
        return [
            'youngest_contact' => ['youngest_contact', 'youngest-unknown-contact'],
            'youngest_known_contact' => ['youngest_known_contact', 'youngest-known-contact'],
            'oldest_contact' => ['oldest_contact', 'oldest-unknown-contact'],
            'oldest_known_contact' => ['oldest_known_contact', 'oldest-known-contact'],
            'contact_with_most_recent_activity' => ['contact_with_most_recent_activity', 'contact-with-most-recent-activity'],
            'known_contact_with_most_recent_activity' => ['known_contact_with_most_recent_activity', 'known-contact-with-most-recent-activity'],
            'all_contacts_with_recent_activity' => [
                'all_contacts_with_recent_activity',
                ['youngest-unknown-contact', 'youngest-known-contact', 'oldest-known-contact', 'contact-with-most-recent-activity', 'known-contact-with-most-recent-activity']
            ],
            'all_known_contacts_with_recent_activity' => ['all_known_contacts_with_recent_activity', ['known-contact-with-most-recent-activity', 'youngest-known-contact', 'oldest-known-contact']],
            'all_contacts' => [
                'all_contacts',
                ['youngest-unknown-contact', 'youngest-known-contact', 'oldest-unknown-contact', 'oldest-known-contact', 'contact-with-most-recent-activity', 'known-contact-with-most-recent-activity']
            ],
            'all_known_contacts' => [
                'all_known_contacts',
                ['youngest-known-contact', 'oldest-known-contact', 'known-contact-with-most-recent-activity']
            ],
        ];
    }
}
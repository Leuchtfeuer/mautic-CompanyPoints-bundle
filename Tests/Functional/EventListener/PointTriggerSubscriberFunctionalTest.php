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
    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->campaginFixtureHelper = new CampaignFixtureHelper($this->em);
    }

    public function testAddLeadToCampaignTriggerAction(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company = $this->fixtureHelper->createCompany("abc");
        $this->createAllLeads($company);
        $campaign = $this->campaginFixtureHelper->createCampaign('Add Lead To Campaign Company Trigger Action');
        $this->campaginFixtureHelper->createCampaignWithScheduledEvent($campaign, 0, 'i');

        $trigger = $this->fixtureHelper->createMembershipActivityTrigger(
            'Tag on new contact first activity',
            CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT
        );

        $this->fixtureHelper->createModifyContactCampaignsAction(
            $trigger,
            'This action should not run',
            [$campaign]
        );

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

        $campaignLeads = $campaign->getLeads();


    }

    private function createAllLeads(Company $company): void
    {
        $defaultLastActiveDate = new \DateTime('-1 hour');
        $defaultDateAddedDate = new \DateTime('-5 days');

        $contact = $this->createContactWithDateAddedAndDateLastActive('youngestunknowncontact@abc.com', (new \DateTime())->modify('-1 hour'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);

        $this->createContactWithDateAddedAndDateLastActive('youngestknowncontact@abc.com', (new \DateTime())->modify('-2 hours'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);

        $contact = $this->createContactWithDateAddedAndDateLastActive('oldestunknowncontact@abc.com', (new \DateTime())->modify('-31 days'), (new \DateTime())->modify('-31 days'));
        $this->fixtureHelper->addContactToCompany($contact, $company);

        $contact = $this->createContactWithDateAddedAndDateLastActive('oldestknowncontact@abc.com', (new \DateTime())->modify('-9 days'), $defaultLastActiveDate);
        $this->fixtureHelper->addContactToCompany($contact, $company);

        $contact = $this->createContactWithDateAddedAndDateLastActive('contactwithmostrecentactivity@abc.com', $defaultDateAddedDate, (new \DateTime())->modify('-20 minutes'));
        $this->fixtureHelper->addContactToCompany($contact, $company);

        $contact = $this->createContactWithDateAddedAndDateLastActive('knowncontactwithmostrecentactivity@abc.com', $defaultDateAddedDate, (new \DateTime())->modify('-30 minutes'));
        $this->fixtureHelper->addContactToCompany($contact, $company);

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

    public function activityEmulationDataProvider(): array
    {
        return [
            'youngest_contact' => [['youngest-unknown-contact@abc.com'], 1],
            'youngest_known_contact'       => [['youngest-known-contact@abc.com'], 1],
            'oldest_contact'       => [['oldest-unknown-contact@abc.com'], 1],
            'oldest_known_contact'       => [['oldest-known-contact@abc.com'], 1],
            'contact_with_most_recent_activity'       => [['contactwith-most-recent-activity@abc.com'], 1],
            'known_contact_with_most_recent_activity'       => [['known-contact-with-most-recent-activity@abc.com'], 1],
            'all_contacts_with_recent_activity'       => [['youngest-unknown-contact@abc.com', 'youngest-known-contact@abc.com', 'oldest-known-contact@abc.com', 'contactwith-most-recent-activity@abc.com', 'known-contact-with-most-recent-activity@abc.com'], 5],
            'all_known_contacts_with_recent_activity'       => ['form_submit_with_tracking@abc.com'],
            'all_contacts'       => [['youngest-unknown-contact@abc.com', 'youngest-known-contact@abc.com', 'oldest-unknown-contact@abc.com', 'oldest-known-contact@abc.com', 'contactwith-most-recent-activity@abc.com', 'known-contact-with-most-recent-activity@abc.com'], 6],
            'all_known_contacts'       => [['youngest-known-contact@abc.com', 'oldest-known-contact@abc.com', 'known-contact-with-most-recent-activity@abc.com'], 3],
        ];
    }
}
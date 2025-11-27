<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\CampaignBundle\Membership\MembershipManager;
use Mautic\LeadBundle\Entity\Company;

class ModifyCampaignsActionHandler
{

    public function __construct(
        private MembershipManager $membershipManager,
    ) {
    }
    /**
     * Executes the action of adding and/or removing tags from a company.
     *
     * @param Company              $company           The company to modify.
     * @param array<string, mixed> $triggerProperties The properties from the trigger event.
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $triggerProperties = $triggerProperties;
        $campaignsToAdd = $triggerProperties['addTo'] ?? [];

/*
        if (!empty($properties['addTo'])) {
            $campaigns = $this->getCampaigns($properties['addTo'], $executingCampaign);

            foreach ($campaigns as $campaign) {
                $this->membershipManager->addContacts(
                    $contacts,
                    $campaign,
                    true
                );
            }
        }

        if (!empty($properties['removeFrom'])) {
            $campaigns = $this->getCampaigns($properties['removeFrom'], $executingCampaign);

            foreach ($campaigns as $campaign) {
                $this->membershipManager->removeContacts(
                    $event->getContactsKeyedById(),
                    $campaign,
                    true
                );
            }
        }
        */
    }
}
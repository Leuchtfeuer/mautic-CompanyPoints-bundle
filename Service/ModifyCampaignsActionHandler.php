<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Membership\MembershipManager;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;

class ModifyCampaignsActionHandler
{
    public function __construct(
        private MembershipManager $membershipManager,
        private CampaignModel $campaignModel,
        private LeadModel $leadModel,
        private CompanyModel $companyModel
    ) {
    }

    /**
     * Executes the action of adding and/or removing tags from a company.
     *
     * @param Company              $company           the company to modify
     * @param array<string, mixed> $triggerProperties the properties from the trigger event
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $campaignIdsToAdd      = $triggerProperties['addToCampaign'] ?? [];
        $campaignIdsToRemove   = $triggerProperties['removeFromCampaign'] ?? [];
        $contactRule           = $triggerProperties['triggerContacts'];
        $contactsToBeProcessed = $this->getContactsByRule($company, $contactRule);

        $campaignsToAdd    = $this->campaignModel->getEntities(['ids' => $campaignIdsToAdd, 'ignore_paginator' => true]);
        $campaignsToRemove = $this->campaignModel->getEntities(['ids' => $campaignIdsToRemove, 'ignore_paginator' => true]);

        if (!empty($contactsToBeProcessed)) {
            $contactsCollection = new ArrayCollection();
            foreach ($contactsToBeProcessed as $contact) {
                $contactsCollection->set($contact->getId(), $contact);
            }

            foreach ($campaignsToAdd as $campaign) {
                $this->membershipManager->addContacts(
                    $contactsCollection,
                    $campaign,
                );
            }

            foreach ($campaignsToRemove as $campaign) {
                $this->membershipManager->removeContacts(
                    $contactsCollection,
                    $campaign,
                );
            }
        }
    }

    private function getContactsByRule(Company $company, string $contactRule): array
    {
        $companyLeadRepo = $this->companyModel->getCompanyLeadRepository();
        $companyLeads    = $companyLeadRepo->getCompanyLeads($company->getId());

        if (empty($companyLeads)) {
            return [];
        }

        $leadIds = array_column($companyLeads, 'lead_id');

        $filter = [
            'force' => [
                ['column' => 'l.id', 'expr' => 'in', 'value' => $leadIds],
            ],
        ];

        switch ($contactRule) {
            case CompanyTriggerEvent::COMPANY_YOUNGEST_CONTACT:
                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.date_added',
                    'orderByDir' => 'DESC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_YOUNGEST_KNOWN_CONTACT:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.date_added',
                    'orderByDir' => 'DESC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_OLDEST_CONTACT:
                // Ältester Kontakt
                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.date_added',
                    'orderByDir' => 'ASC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_OLDEST_KNOWN_CONTACT:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.date_added',
                    'orderByDir' => 'ASC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_CONTACT:
                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.last_active',
                    'orderByDir' => 'DESC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_KNOWN_CONTACT:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.last_active',
                    'orderByDir' => 'DESC',
                    'limit'      => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS:
                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS_WITH_RECENT_ACTIVITY:
                $filter['force'][] = [
                    'column' => 'l.last_active',
                    'expr'   => 'gte',
                    'value'  => (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS_WITH_RECENT_ACTIVITY:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];
                $filter['force'][] = [
                    'column' => 'l.last_active',
                    'expr'   => 'gte',
                    'value'  => (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            default:
                return [];
        }
    }
}

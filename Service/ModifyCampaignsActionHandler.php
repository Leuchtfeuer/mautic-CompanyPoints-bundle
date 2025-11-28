<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\CampaignBundle\Membership\MembershipManager;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\CompanyModel;
use Doctrine\Common\Collections\ArrayCollection;


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
     * @param Company              $company           The company to modify.
     * @param array<string, mixed> $triggerProperties The properties from the trigger event.
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $campaignsToAdd = $triggerProperties['addToCampaign'] ?? [];
        $campaignsToRemove = $triggerProperties['removeFromCampaign'] ?? [];
        $contactRule = $triggerProperties['triggerContacts'];
        $contactsToBeAdded = $this->getContactsByRule($company, $contactRule);


        $campaigns = $this->campaignModel->getEntities(['ids' => $campaignsToAdd, 'ignore_paginator' => true]);

        if (!empty($contactsToBeAdded) && !empty($campaigns)) {
            $contactsCollection = new ArrayCollection($contactsToBeAdded);

            foreach ($campaigns as $campaign) {
                $this->membershipManager->addContacts(
                    $contactsCollection,
                    $campaign,
                );
            }
        }
    }

    private function getContactsByRule(Company $company, string $contactRule): array
    {
        // Hole alle Lead-IDs für dieses Unternehmen
        $companyLeadRepo = $this->companyModel->getCompanyLeadRepository();
        $companyLeads = $companyLeadRepo->getCompanyLeads($company->getId());

        if (empty($companyLeads)) {
            return [];
        }

        $leadIds = array_column($companyLeads, 'lead_id');

        // Basis-Filter: Nur Kontakte dieses Unternehmens
        $filter = [
            'force' => [
                ['column' => 'l.id', 'expr' => 'in', 'value' => $leadIds],
            ],
        ];

        switch ($contactRule) {
            case CompanyTriggerEvent::COMPANY_YOUNGEST_CONTACT:
                // Jüngster Kontakt = nach date_added sortiert, neueste zuerst
                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.date_added',
                    'orderByDir' => 'DESC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_YOUNGEST_KNOWN_CONTACT:
                // Jüngster bekannter Kontakt = identifiziert + nach date_added sortiert
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr' => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.date_added',
                    'orderByDir' => 'DESC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_OLDEST_CONTACT:
                // Ältester Kontakt
                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.date_added',
                    'orderByDir' => 'ASC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_OLDEST_KNOWN_CONTACT:
                // Ältester bekannter Kontakt
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr' => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.date_added',
                    'orderByDir' => 'ASC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_CONTACT:
                // Kontakt mit neuester Aktivität
                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.last_active',
                    'orderByDir' => 'DESC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_KNOWN_CONTACT:
                // Bekannter Kontakt mit neuester Aktivität
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr' => 'isNotNull',
                ];

                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                    'orderBy' => 'l.last_active',
                    'orderByDir' => 'DESC',
                    'limit' => 1,
                ]);

                return !empty($results) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS:
                // Alle Kontakte
                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS:
                // Alle bekannten Kontakte
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr' => 'isNotNull',
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS_WITH_RECENT_ACTIVITY:
                // Alle Kontakte mit kürzlicher Aktivität (hier müsstest du definieren, was "recent" bedeutet)
                // Beispiel: Aktivität in den letzten 30 Tagen
                $filter['force'][] = [
                    'column' => 'l.last_active',
                    'expr' => 'gte',
                    'value' => (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            case CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS_WITH_RECENT_ACTIVITY:
                // Alle bekannten Kontakte mit kürzlicher Aktivität
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr' => 'isNotNull',
                ];
                $filter['force'][] = [
                    'column' => 'l.last_active',
                    'expr' => 'gte',
                    'value' => (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
                ];

                return $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

            default:
                return [];
        }
    }
}
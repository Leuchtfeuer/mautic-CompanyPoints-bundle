<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Membership\MembershipManager;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeadsRepository;

class ModifyCampaignsActionHandler
{
    public function __construct(
        private MembershipManager $membershipManager,
        private CampaignModel $campaignModel,
        private LeadModel $leadModel,
        private CompanyModel $companyModel,
        private CompaniesPlaceholderLeadsRepository $companiesPlaceholderLeadsRepository,
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
        $campaignIdsToAdd          = $triggerProperties['addToCampaign'] ?? [];
        $campaignIdsToRemove       = $triggerProperties['removeFromCampaign'] ?? [];
        $campaignIdsToRestartOrAdd = $triggerProperties['restartOrAddToCampaign'] ?? [];

        $contactRule           = $triggerProperties['triggerContacts'];
        if (!is_string($contactRule)) {
            return;
        }
        $contactsToBeProcessed = $this->getContactsByRule($company, $contactRule);

        $campaignsToAdd    = $this->campaignModel->getEntities(['ids' => $campaignIdsToAdd, 'ignore_paginator' => true]);
        if (!empty($contactsToBeProcessed)) {
            $contactsCollection = new ArrayCollection();
            foreach ($contactsToBeProcessed as $contact) {
                $contactsCollection->set($contact->getId(), $contact);
            }

            foreach ($campaignsToAdd as $campaign) {
                // Copy of contactsCollection is necessary as addContacts and removeContacts modify the collection
                $contactsCopy = new ArrayCollection($contactsCollection->toArray());
                $this->membershipManager->addContacts(
                    $contactsCopy,
                    $campaign,
                );
            }

            $campaignsToRemove = $this->campaignModel->getEntities(['ids' => $campaignIdsToRemove, 'ignore_paginator' => true]);
            foreach ($campaignsToRemove as $campaign) {
                $contactsCopy = new ArrayCollection($contactsCollection->toArray());
                $this->membershipManager->removeContacts(
                    $contactsCopy,
                    $campaign,
                );
            }

            $campaignsToRestartOrAdd = $this->campaignModel->getEntities(['ids' => $campaignIdsToRestartOrAdd, 'ignore_paginator' => true]);
            foreach ($campaignsToRestartOrAdd as $campaign) {
                $contactsCopy = new ArrayCollection($contactsCollection->toArray());
                $this->membershipManager->removeContacts(
                    $contactsCopy,
                    $campaign,
                );
                $contactsCopy = new ArrayCollection($contactsCollection->toArray());
                $this->membershipManager->addContacts(
                    $contactsCopy,
                    $campaign,
                );
            }
        }
    }

    /**
     * @return array<Lead>
     */
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

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

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

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_OLDEST_CONTACT:
                // Ältester Kontakt
                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.date_added',
                    'orderByDir' => 'ASC',
                    'limit'      => 1,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

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

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_CONTACT:
                $results = $this->leadModel->getEntities([
                    'filter'     => $filter,
                    'orderBy'    => 'l.last_active',
                    'orderByDir' => 'DESC',
                    'limit'      => 1,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

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

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS:
                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS:
                $filter['force'][] = [
                    'column' => 'l.date_identified',
                    'expr'   => 'isNotNull',
                ];

                $results =  $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::COMPANY_ALL_CONTACTS_WITH_RECENT_ACTIVITY:
                $filter['force'][] = [
                    'column' => 'l.last_active',
                    'expr'   => 'gte',
                    'value'  => (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
                ];

                $results =  $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

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

                $results = $this->leadModel->getEntities([
                    'filter' => $filter,
                ]);

                return (is_array($results) && !empty($results)) ? array_values($results) : [];

            case CompanyTriggerEvent::PLACEHOLDER_CONTACT:
                $placeholderLead = $this->companiesPlaceholderLeadsRepository->getPrimaryLeadOfCompany($company->getId()) ?? [];

                return ($placeholderLead instanceof Lead) ? [$placeholderLead] : [];

            default:
                return [];
        }
    }
}

<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;

class ModifyCompanySegmentsActionHandler
{
    public function __construct(
        private CompanySegmentModel $companySegmentModel,
    ) {
    }
    /**
     * @param Company              $company
     * @param array<string, mixed> $triggerProperties
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $segmentIdsToAdd          = $triggerProperties['add_segments'] ?? [];
        $segmentIdsToRemove       = $triggerProperties['remove_segments'] ?? [];

        if (is_array($segmentIdsToAdd) && !empty($segmentIdsToAdd)) {
            /** @var array<int> $segmentIdsToAddTyped */
            $segmentIdsToAddTyped = array_map('intval', $segmentIdsToAdd);
            $segmentsToAdd = $this->companySegmentModel->getRepository()->getSegmentObjectsViaListOfIDs($segmentIdsToAddTyped);
        }

        if (is_array($segmentIdsToRemove) && !empty($segmentIdsToRemove)) {
            /** @var array<int> $segmentIdsToRemoveTyped */
            $segmentIdsToRemoveTyped = array_map('intval', $segmentIdsToRemove);
            $segmentsToRemove = $this->companySegmentModel->getRepository()->getSegmentObjectsViaListOfIDs($segmentIdsToRemoveTyped);
        }

        if (!empty($segmentsToAdd)){
            $this->companySegmentModel->addCompany($company, $segmentsToAdd);
        }

        if(!empty($segmentsToRemove)) {
            $this->companySegmentModel->removeCompany($company, $segmentsToRemove);
        }
    }
}
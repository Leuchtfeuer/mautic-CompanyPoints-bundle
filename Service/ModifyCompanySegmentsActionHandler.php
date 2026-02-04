<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;

class ModifyCompanySegmentsActionHandler
{
    public function __construct(
        private CompanySegmentRepository $companySegmentRepository,
        private CompanySegmentModel $companySegmentModel,
    ) {
    }

    /**
     * @param array<string, mixed> $triggerProperties
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $segmentsToAdd            = [];
        $segmentsToRemove         = [];
        $segmentIdsToAdd          = $triggerProperties['add_segments'] ?? [];
        $segmentIdsToRemove       = $triggerProperties['remove_segments'] ?? [];

        if (is_array($segmentIdsToAdd) && !empty($segmentIdsToAdd)) {
            /** @var array<int> $segmentIdsToAddTyped */
            $segmentIdsToAddTyped = array_map('intval', $segmentIdsToAdd);
            $segmentsToAdd        = $this->companySegmentRepository->getSegmentObjectsViaListOfIDs($segmentIdsToAddTyped);
        }

        if (is_array($segmentIdsToRemove) && !empty($segmentIdsToRemove)) {
            /** @var array<int> $segmentIdsToRemoveTyped */
            $segmentIdsToRemoveTyped = array_map('intval', $segmentIdsToRemove);
            $segmentsToRemove        = $this->companySegmentRepository->getSegmentObjectsViaListOfIDs($segmentIdsToRemoveTyped);
        }

        if ([] !== $segmentsToAdd) {
            $this->companySegmentModel->addCompany($company, $segmentsToAdd);
        }

        if ([] !== $segmentsToRemove) {
            $this->companySegmentModel->removeCompany($company, $segmentsToRemove);
        }
    }
}

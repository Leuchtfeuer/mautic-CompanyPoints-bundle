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
     * Executes the action of adding and/or removing tags from a company.
     *
     * @param Company              $company           the company to modify
     * @param array<string, mixed> $triggerProperties the properties from the trigger event
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $segmentIdsToAdd          = $triggerProperties['addToCompanySegments'] ?? [];
        $segmentIdsToRemove       = $triggerProperties['removeFromCompanySegments'] ?? [];
        if (is_array($segmentIdsToAdd) && !empty($segmentIdsToAdd)) {
            $segmentsToAdd = $this->companySegmentModel->getRepository()->getSegmentObjectsViaListOfIDs($segmentIdsToAdd);
            // Get segments the company already possesses and remove them from $segmentsToAdd to avoid adding duplicates. --> Even necessary?
        }

        if (is_array($segmentIdsToRemove) && !empty($segmentIdsToRemove)) {
            $segmentsToRemove = $this->companySegmentModel->getRepository()->getSegmentObjectsViaListOfIDs($segmentIdsToRemove);
        }

        // Execute the update only if there are tags to add or remove.
        if (!empty($tagsToAdd) || !empty($tagsToRemove)) {
            // Use array_values to re-index the array after potential `unset` operations.
            $this->companySegmentModel->addCompany($company, $segmentsToAdd);
            $this->companySegmentModel->removeCompany($company, $segmentsToRemove);
        }
    }
}
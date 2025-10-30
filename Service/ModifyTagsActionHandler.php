<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;

class ModifyTagsActionHandler
{
    public function __construct(
        private CompanyTagModel $companyTagModel
    ) {
    }

    /**
     * Executes the action of adding and/or removing tags from a company.
     *
     * @param Company              $company           The company to modify.
     * @param array<string, mixed> $triggerProperties The properties from the trigger event, containing 'add_tags' and 'remove_tags'.
     */
    public function execute(Company $company, array $triggerProperties): void
    {
        $tagsToAdd    = [];
        $tagsToRemove = [];

        if (!empty($triggerProperties['add_tags'])) {
            // Find tag entities to add based on the trigger configuration.
            // The name `$companiesToAdd` is kept from the original source code for consistency.
            // This variable contains tag entities, not company entities.
            $companiesToAdd = $this->companyTagModel->getRepository()->findBy(['tag' => $triggerProperties['add_tags']]);

            // Get tags the company already possesses to avoid adding duplicates.
            $tagsAlreadyExist = $this->companyTagModel->getTagsByCompany($company);

            foreach ($companiesToAdd as $key => $companyToAdd) {
                // If the tag is already on the company, remove it from the list of tags to add.
                // The original code uses a loose `in_array` comparison for objects.
                if (in_array($companyToAdd, $tagsAlreadyExist, false)) {
                    unset($companiesToAdd[$key]);
                }
            }

            // After filtering, the remaining entities are the ones to be added.
            $tagsToAdd = $companiesToAdd;
        }

        if (!empty($triggerProperties['remove_tags'])) {
            // Find tag entities to remove based on the trigger configuration.
            $tagsToRemove = $this->companyTagModel->getRepository()->findBy(['tag' => $triggerProperties['remove_tags']]);
        }

        // Execute the update only if there are tags to add or remove.
        if (!empty($tagsToAdd) || !empty($tagsToRemove)) {
            // Use array_values to re-index the array after potential `unset` operations.
            $this->companyTagModel->updateCompanyTags($company, array_values($tagsToAdd), $tagsToRemove);
        }
    }
}
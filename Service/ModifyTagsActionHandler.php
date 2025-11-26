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
            $tags = $this->companyTagModel->getRepository()->findBy(['tag' => $triggerProperties['add_tags']]);

            // Get tags the company already possesses to avoid adding duplicates.
            $tagsAlreadyExist = $this->companyTagModel->getTagsByCompany($company);

            foreach ($tags as $key => $tag) {
                // If the tag is already on the company, remove it from the list of tags to add.
                if (in_array($tag, $tagsAlreadyExist)) {
                    unset($tags[$key]);
                }
            }
            $tagsToAdd = $tags;
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
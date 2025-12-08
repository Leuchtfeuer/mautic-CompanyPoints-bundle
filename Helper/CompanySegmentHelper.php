<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;

class CompanySegmentHelper
{
    public function __construct(
        private CompanySegmentModel $companySegmentModel,

    ) {
    }

    public function companyHasCorrectSegmentMembership(Company $company, array $companySegmentMembershipFilter): bool
    {
        $operator = $companySegmentMembershipFilter['operator'] ?? null;
        if(null === $operator) {
            return true;
        }

        $segmentIds = $companySegmentMembershipFilter['segments'] ?? [];
        if (count($companySegmentMembershipFilter['segments']) === 0) {
            return true;
        }

        $companySegments = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(
            [
                'company'        => $company,
                'companySegment' => $segmentIds,
                'manuallyRemoved' => false,
            ]
        );

        $isInSegment = is_array($companySegments) && count($companySegments) > 0;
        return match ($operator) {
            'in'    => $isInSegment,
            'notIn' => !$isInSegment,
            default => true,
        };
    }
}
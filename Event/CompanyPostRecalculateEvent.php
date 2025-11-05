<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event;

use Mautic\LeadBundle\Entity\Company;

class CompanyPostRecalculateEvent
{
    public function __construct(
        private Company $company
    ) {
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}

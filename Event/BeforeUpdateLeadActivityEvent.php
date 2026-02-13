<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event;

use Mautic\LeadBundle\Entity\Lead;

class BeforeUpdateLeadActivityEvent
{
    public function __construct(
        public Lead $lead,
        public \DateTimeInterface $activityDate,
    ) {
    }
}

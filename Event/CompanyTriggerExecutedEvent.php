<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent as CompanyTriggerEventEntity;
use Symfony\Contracts\EventDispatcher\Event;

class CompanyTriggerExecutedEvent extends Event
{
    private ?bool $result = null;

    public function __construct(
        private CompanyTriggerEventEntity $triggerEvent,
        private Lead $lead
    ) {
    }

    /**
     * @return CompanyTriggerEventEntity
     */
    public function getTriggerEvent()
    {
        return $this->triggerEvent;
    }

    /**
     * @return Lead
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * @return bool
     */
    public function getResult()
    {
        return $this->result;
    }

    public function setSucceded(): void
    {
        $this->result = true;
    }

    public function setFailed(): void
    {
        $this->result = false;
    }
}

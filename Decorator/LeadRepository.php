<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Decorator;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository as CoreLeadRepository;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\BeforeUpdateLeadActivityEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class LeadRepository extends CoreLeadRepository
{
    public function __construct(
        ManagerRegistry $registry,
        EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($registry, Lead::class);
        $this->setDispatcher($dispatcher);
    }

    public function updateLastActive($leadId, ?\DateTimeInterface $lastActiveDate = null): void
    {
        if (!$leadId) {
            return;
        }

        $lead = parent::getEntity($leadId);
        if ($lead) {
            $event = new BeforeUpdateLeadActivityEvent($lead, $lastActiveDate ?? new \DateTimeImmutable());
            $this->dispatcher->dispatch($event);
            parent::updateLastActive($leadId, $lastActiveDate);
        }
    }
}
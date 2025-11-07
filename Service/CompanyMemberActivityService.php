<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;

class CompanyMemberActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function isLeadFirstActivity(Lead $lead): bool
    {
        $changes = $lead->getChanges(true);
        return array_key_exists('dateLastActive', $changes) && $changes['dateLastActive'][0] === null;
    }

    /**
     * Counts relevant lead activities associated with a company.
     * An activity is only considered if it occurred after the lead was added to the company.
     *
     * @param int|null $withinDays If null, counts all activities ever.
     *                             If an integer, counts activities within the last N days.
     */
    public function countLeadActivities(Company $company, ?int $withinDays = null, ?Lead $excludeLead = null): int
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->select('COUNT(l.id)')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl')
            ->join('cl', MAUTIC_TABLE_PREFIX.'leads', 'l', 'cl.lead_id = l.id')
            ->where($qb->expr()->eq('cl.company_id', ':companyId'))
            ->andWhere($qb->expr()->gte('l.last_active', 'cl.date_added'))
            ->setParameter('companyId', $company->getId());

        if (null !== $excludeLead) {
            $qb->andWhere($qb->expr()->neq('cl.lead_id', ':excludeLeadId'))
                ->setParameter('excludeLeadId', $excludeLead->getId());
        }

        if (null !== $withinDays) {
            $qb->andWhere(
                $qb->expr()->gte('l.last_active', ':date')
            );
            $date = new \DateTime();
            $date->modify('-'.abs($withinDays).' days');
            $qb->setParameter('date', $date->format('Y-m-d H:i:s'));
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function isJoinCoincidingWithActivity(Lead $lead, Company $company, int $thresholdInSeconds = 5): bool
    {
        $lastActive = $lead->getLastActive();
        if (null === $lastActive) {
            // No activity, so they can't coincide.
            return false;
        }

        $associationDate = $this->getAssociationDate($lead, $company);
        if (null === $associationDate) {
            // Should not happen if called from LeadCompanyChangeEvent, but good to have.
            return false;
        }

        $difference = abs($lastActive->getTimestamp() - $associationDate->getTimestamp());

        return $difference <= $thresholdInSeconds;
    }

    /**
     * Fetches the timestamp when a lead was added to a company.
     */
    private function getAssociationDate(Lead $lead, Company $company): ?\DateTimeImmutable
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->select('cl.date_added')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl')
            ->where($qb->expr()->eq('cl.lead_id', ':leadId'))
            ->andWhere($qb->expr()->eq('cl.company_id', ':companyId'))
            ->setParameter('leadId', $lead->getId())
            ->setParameter('companyId', $company->getId())
            ->orderBy('cl.date_added', 'DESC')
            ->setMaxResults(1);

        $dateAdded = $qb->executeQuery()->fetchOne();

        return $dateAdded ? new \DateTimeImmutable($dateAdded) : null;
    }
}
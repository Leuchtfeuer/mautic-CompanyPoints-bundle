<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Company;

class CompanyMemberActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }


    /**
     * Checks if the company had any contact activity at all.
     */
    public function hasAnyLeadActivity(Company $company): bool
    {
        return $this->queryForLeadActivity($company);
    }

    /**
     * Checks if the company had any contact activity within the last 30 days.
     */
    public function hasLeadActivityWithin30Days(Company $company): bool
    {
        return $this->queryForLeadActivity($company, 30);
    }

    /**
     * Queries for lead activity associated with a company.
     *
     * @param int|null $withinDays If null, checks for any activity ever.
     *                             If an integer, checks for activity within the last N days.
     */
    private function queryForLeadActivity(Company $company, ?int $withinDays = null): bool
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->select('1')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl')
            ->join('cl', MAUTIC_TABLE_PREFIX.'leads', 'l', 'cl.lead_id = l.id')
            ->where($qb->expr()->eq('cl.company_id', ':companyId'))
            // count activity that happened AFTER the lead was added to the company
            ->andWhere($qb->expr()->gt('l.last_active', 'cl.date_added'))
            ->setParameter('companyId', $company->getId())
            ->setMaxResults(1);

        if (null !== $withinDays) {
            // Further restrict to activity within a specific timeframe
            $qb->andWhere(
                $qb->expr()->gte('l.last_active', ':date')
            );
            $date = new \DateTime();
            // Ensure we use a positive integer
            $date->modify('-'.abs($withinDays).' days');
            $qb->setParameter('date', $date->format('Y-m-d H:i:s'));
        }

        $sql = $qb->getSQL();
        $result = $qb->executeQuery()->fetchOne();
        return (bool) $result;
    }

}
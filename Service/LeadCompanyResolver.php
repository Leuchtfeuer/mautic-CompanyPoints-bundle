<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;

class LeadCompanyResolver
{
    public function __construct(
        private Connection $db,
        private CompanyRepository $companyRepository,
    ) {
    }

    public function getPrimaryCompanyByLead(Lead $lead): ?Company
    {
        $leadId = $lead->getId();
        if (!$leadId) {
            return null;
        }

        $qb = $this->db->createQueryBuilder();
        $qb->select('cl.company_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl')
            ->where('cl.lead_id = :lead')
            ->andWhere('cl.is_primary = 1')
            ->setParameter('lead', $leadId)
            ->setMaxResults(1);

        $id = $qb->executeQuery()->fetchOne();

        if (!$id) {
            return null;
        }

        return $this->companyRepository->find((int) $id);
    }
}

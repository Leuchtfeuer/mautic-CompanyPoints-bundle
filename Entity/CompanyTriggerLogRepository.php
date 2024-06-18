<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyTriggerLogRepository>
 */
class CompanyTriggerLogRepository extends CommonRepository
{
    /**
     * Updates lead ID (e.g. after a lead merge).
     */
    public function updateLead($fromCompanyId, $toCompanyId): void
    {
        // First check to ensure the $toLead doesn't already exist
        $results = $this->_em->getConnection()->createQueryBuilder()
            ->select('pl.event_id')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_event_log', 'pl')
            ->where('pl.company_id = '.$toCompanyId)
            ->executeQuery()
            ->fetchAllAssociative();

        $events  = [];
        foreach ($results as $r) {
            $events[] = $r['event_id'];
        }

        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'company_point_company_event_log')
            ->set('company_id', (int) $toCompanyId)
            ->where('company_id = '.(int) $fromCompanyId);

        if (!empty($events)) {
            $q->andWhere(
                $q->expr()->notIn('event_id', $events)
            )->executeStatement();

            // Delete remaining leads as the new lead already belongs
            $this->_em->getConnection()->createQueryBuilder()
                ->delete(MAUTIC_TABLE_PREFIX.'company_point_company_lead_event_log')
                ->where('company_id = '.(int) $fromCompanyId)
                ->executeStatement();
        } else {
            $q->executeStatement();
        }
    }
}

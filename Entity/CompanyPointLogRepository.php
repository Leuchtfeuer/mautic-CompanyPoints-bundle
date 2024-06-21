<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyPointLogRepository>
 */
class CompanyPointLogRepository extends CommonRepository
{
    /**
     * Updates lead ID (e.g. after a lead merge).
     */
    public function updateLead($fromCompanyId, $toCompanyId): void
    {
        // First check to ensure the $toLead doesn't already exist
        $results = $this->_em->getConnection()->createQueryBuilder()
            ->select('pl.point_id')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_action_log', 'pl')
            ->where('pl.company_id = '.$toCompanyId)
            ->executeQuery()
            ->fetchAllAssociative();

        $actions = [];
        foreach ($results as $r) {
            $actions[] = $r['point_id'];
        }

        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'company_point_company_action_log')
            ->set('company_id', (int) $toCompanyId)
            ->where('company_id = '.(int) $fromCompanyId);

        if (!empty($actions)) {
            $q->andWhere(
                $q->expr()->notIn('point_id', $actions)
            )->executeStatement();

            // Delete remaining leads as the new lead already belongs
            $this->_em->getConnection()->createQueryBuilder()
                ->delete(MAUTIC_TABLE_PREFIX.'company_point_company_action_log')
                ->where('company_id = '.(int) $fromCompanyId)
                ->executeStatement();
        } else {
            $q->executeStatement();
        }
    }
}

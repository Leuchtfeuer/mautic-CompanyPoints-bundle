<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Mautic\CoreBundle\Entity\CommonRepository;

// use Mautic\PointBundle\Entity\GroupContactScore;

/**
 * @extends CommonRepository<CompanyTriggerEvent>
 */
class CompanyTriggerEventRepository extends CommonRepository
{
    /**
     * Get array of published triggers based on point total.
     *
     * @param int $points
     *
     * @return array
     */
    public function getPublishedByPointTotal($points)
    {
        $q = $this->createQueryBuilder('a')
            ->select('partial a.{id, type, name, properties}, partial r.{id, name, points, color}')
            ->leftJoin('a.trigger', 'r')
            ->orderBy('a.order,r.points');

        // make sure the published up and down dates are good
        $expr = $this->getPublishedByDateExpression($q, 'r');

        $expr->add(
            $q->expr()->lte('r.points', (int) $points)
        );

        $q->where($expr);
        $q->andWhere('r.group IS NULL');

        return $q->getQuery()->getArrayResult();
    }

    //    /**
    //     * @param ArrayCollection<int,GroupContactScore> $groupScores
    //     *
    //     * @return mixed[]
    //     */
    //    public function getPublishedByGroupScore(Collection $groupScores)
    //    {
    //        if ($groupScores->isEmpty()) {
    //            return [];
    //        }
    //
    //        $q = $this->createQueryBuilder('a')
    //            ->select('partial a.{id, type, name, properties}, partial r.{id, name, points, color}, partial pl.{id, name}')
    //            ->leftJoin('a.trigger', 'r')
    //            ->leftJoin('r.group', 'pl')
    //            ->orderBy('a.order');
    //
    //        // make sure the published up and down dates are good
    //        $expr = $this->getPublishedByDateExpression($q, 'r');
    //
    //        $groupsExpr = $q->expr()->orX();
    //        /** @var GroupContactScore $score */
    //        foreach ($groupScores as $score) {
    //            $groupsExpr->add(
    //                $q->expr()->andX(
    //                    $q->expr()->eq('pl.id', $score->getGroup()->getId()),
    //                    $q->expr()->lte('r.points', $score->getScore())
    //                )
    //            );
    //        }
    //
    //        $q->where($expr);
    //        $q->andWhere($groupsExpr);
    //        $q->andWhere('r.group IS NOT NULL');
    //
    //        return $q->getQuery()->getArrayResult();
    //    }

    /**
     * Get array of published actions based on type.
     *
     * @param string $type
     *
     * @return array
     */
    public function getPublishedByType($type)
    {
        $q = $this->createQueryBuilder('e')
            ->select('partial e.{id, type, name, properties}, partial t.{id, name, points, color}')
            ->join('e.trigger', 't')
            ->orderBy('e.order');

        // make sure the published up and down dates are good
        $expr = $this->getPublishedByDateExpression($q, 't');
        $expr->add(
            $q->expr()->eq('e.type', ':type')
        );
        $q->where($expr)
            ->setParameter('type', $type);

        return $q->getQuery()->getResult();
    }

    /**
     * @param int $companyId
     */
    public function getLeadTriggeredEvents($companyId): array
    {
        $q = $this->_em->getConnection()->createQueryBuilder()
            ->select('e.*')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_event_log', 'x')
            ->innerJoin('x', MAUTIC_TABLE_PREFIX.'company_point_trigger_events', 'e', 'x.event_id = e.id')
            ->innerJoin('e', MAUTIC_TABLE_PREFIX.'company_point_triggers', 't', 'e.trigger_id = t.id');

        // make sure the published up and down dates are good
        $q->where($q->expr()->eq('x.company_id', (int) $companyId));

        $results = $q->executeQuery()->fetchAllAssociative();

        $return = [];

        foreach ($results as $r) {
            $return[$r['id']] = $r;
        }

        return $return;
    }

    /**
     * @param int $eventId
     */
    public function getCompaniesForEvent($eventId): array
    {
        $results = $this->_em->getConnection()->createQueryBuilder()
            ->select('e.company_id')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_event_log', 'e')
            ->where('e.event_id = '.(int) $eventId)
            ->executeQuery()
            ->fetchAllAssociative();

        $return = [];

        foreach ($results as $r) {
            $return[] = $r['company_id'];
        }

        return $return;
    }
}

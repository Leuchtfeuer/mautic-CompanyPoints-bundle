<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyPoint>
 */
class CompanyPointRepository extends CommonRepository
{
    public function getEntities(array $args = [])
    {
        $q = $this->_em
            ->createQueryBuilder()
            ->select($this->getTableAlias().', cat')
            ->from(CompanyPoint::class, $this->getTableAlias())
            ->leftJoin($this->getTableAlias().'.category', 'cat');
        //            ->leftJoin($this->getTableAlias().'.group', 'pl');

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    public function getTableAlias(): string
    {
        return 'p';
    }

    /**
     * Get array of published actions based on type.
     *
     * @param string $type
     *
     * @return array
     */
    public function getPublishedByType($type)
    {
        $q = $this->createQueryBuilder('p')
            ->select('partial p.{id, type, name, delta, repeatable, properties}')
            ->setParameter('type', $type);

        // make sure the published up and down dates are good
        $expr = $this->getPublishedByDateExpression($q);
        $expr->add($q->expr()->eq('p.type', ':type'));

        $q->where($expr);

        return $q->getQuery()->getResult();
    }

    /**
     * @param string $type
     * @param int    $companyId
     */
    public function getCompletedLeadActions($type, $companyId): array
    {
        $q = $this->_em->getConnection()->createQueryBuilder()
            ->select('p.*')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_action_log', 'x')
            ->innerJoin('x', MAUTIC_TABLE_PREFIX.'company_points', 'p', 'x.point_id = p.id');

        // make sure the published up and down dates are good
        $q->where(
            $q->expr()->and(
                $q->expr()->eq('p.type', ':type'),
                $q->expr()->eq('x.company_id', (int) $companyId)
            )
        )
            ->setParameter('type', $type);

        $results = $q->executeQuery()->fetchAllAssociative();

        $return = [];

        foreach ($results as $r) {
            $return[$r['id']] = $r;
        }

        return $return;
    }

    /**
     * @param int $companyId
     */
    public function getCompletedLeadActionsByLeadId($companyId): array
    {
        $q = $this->_em->getConnection()->createQueryBuilder()
            ->select('p.*')
            ->from(MAUTIC_TABLE_PREFIX.'company_point_company_action_log', 'x')
            ->innerJoin('x', MAUTIC_TABLE_PREFIX.'company_points', 'p', 'x.point_id = p.id');

        // make sure the published up and down dates are good
        $q->where(
            $q->expr()->and(
                $q->expr()->eq('x.company_id', (int) $companyId)
            )
        );

        $results = $q->executeQuery()->fetchAllAssociative();

        $return = [];

        foreach ($results as $r) {
            $return[$r['id']] = $r;
        }

        return $return;
    }

    protected function addCatchAllWhereClause($q, $filter): array
    {
        return $this->addStandardCatchAllWhereClause($q, $filter, [
            'p.name',
            'p.description',
        ]);
    }

    protected function addSearchCommandWhereClause($q, $filter): array
    {
        return $this->addStandardSearchCommandWhereClause($q, $filter);
    }

    /**
     * @return string[]
     */
    public function getSearchCommands(): array
    {
        return $this->getStandardSearchCommands();
    }
}

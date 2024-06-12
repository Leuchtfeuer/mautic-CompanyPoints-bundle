<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyTrigger>
 */
class CompanyTriggerRepository extends CommonRepository
{
    public function getEntities(array $args = [])
    {
        $q = $this->_em
            ->createQueryBuilder()
            ->select($this->getTableAlias().', cat')
            ->from(CompanyTrigger::class, $this->getTableAlias())
            ->leftJoin($this->getTableAlias().'.category', 'cat');
//            ->leftJoin($this->getTableAlias().'.group', 'pl');

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * Get a list of published triggers with color and points.
     *
     * @return array
     */
    public function getTriggerColors()
    {
        $q = $this->_em->createQueryBuilder()
            ->select('partial t.{id, color, points}')
            ->from(CompanyTrigger::class, 't', 't.id');

        $q->where($this->getPublishedByDateExpression($q));

        $q->orderBy('t.points', \Doctrine\Common\Collections\Criteria::ASC);

        return $q->getQuery()->getArrayResult();
    }

    public function getTableAlias(): string
    {
        return 't';
    }

    protected function addCatchAllWhereClause($q, $filter): array
    {
        return $this->addStandardCatchAllWhereClause($q, $filter, [
            't.name',
            't.description',
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

    public function getTriggersPublished(): array
    {
        $q = $this->_em->createQueryBuilder()
            ->select('t')
            ->from(CompanyTrigger::class, 't');

        $q->where($this->getPublishedByDateExpression($q));

        return $q->getQuery()->getResult();
    }
}

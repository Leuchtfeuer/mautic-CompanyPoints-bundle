<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Helper\EmailValidator;
use Mautic\LeadBundle\Deduplicate\CompanyDeduper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CompanyScoreModel extends CompanyModel
{
    public function __construct(
        FieldModel $leadFieldModel,
        EmailValidator $emailValidator,
        CompanyDeduper $companyDeduper,
        EntityManager $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper,
        protected LeadModel $leadModel
    ) {
        parent::__construct($leadFieldModel, $emailValidator, $companyDeduper, $em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper);
    }

    public function recalculateCompanyScores(Company $company): ?int
    {
        $score = $company->getScore();
        $leads = $this->getLeadsByCompany($company);

        if (empty($leads)) {
            $this->setFieldValues($company, ['score_calculated' => $score]);
            $this->saveEntity($company);

            return $score;
        }

        $totalLeadsValid = 0;
        foreach ($leads as $lead) {
            if (empty($lead->getPoints())) {
                continue;
            }
            $score += $lead->getPoints();
            ++$totalLeadsValid;
        }

        $resultScore = $score;
        if (!empty($totalLeadsValid)) {
            $resultScore = $score / $totalLeadsValid;
        }

        if (fmod($resultScore, 1)) {
            $resultScore = floor($resultScore) + 1;
        }

        $this->setFieldValues($company, ['score_calculated' => $resultScore]);
        $this->saveEntity($company);

        return $score;
    }

    /**
     * @return array<\Mautic\LeadBundle\Entity\Company>
     */
    public function getCompanies(int $limit = 0, int $offset = 0): array
    {
        $qb = $this->getRepository()->createQueryBuilder('c');
        $qb->select('c')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<\Mautic\LeadBundle\Entity\Lead>
     */
    public function getLeadsByCompany(Company $company): array
    {
        $companyId = $company->getId();
        $q         = $this->em->getConnection()->createQueryBuilder();
        $q->select('cl.lead_id,cl.lead_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl');

        $q->where($q->expr()->eq('cl.company_id', ':company'))
            ->setParameter('company', $companyId);

        $leads =  $q->executeQuery()->fetchAllKeyValue();

        if (empty($leads)) {
            return [];
        }

        return $this->leadModel->getRepository()->findBy(['id' => $leads]);
    }
}

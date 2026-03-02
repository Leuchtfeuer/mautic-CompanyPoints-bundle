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
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Field\FieldList;
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
        FieldList $fieldList,
        protected LeadModel $leadModel,
    ) {
        parent::__construct($leadFieldModel, $emailValidator, $companyDeduper, $em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper, $fieldList);
    }

    public function recalculateCompanyScores(Company $company): ?int
    {
        $currentScore = (int) ($company->getField('companyscore_calculated')['value'] ?? 0);
        $companyScore = $company->getScore();
        $leads        = $this->getLeadsByCompany($company);

        if (empty($leads)) {
            $resultScore = $companyScore;
            if ((int) $resultScore !== $currentScore) {
                $this->setFieldValues($company, ['companyscore_calculated' => $resultScore]);
                $this->saveEntity($company);
            }

            return $resultScore;
        }

        $leadPoints      = 0;
        $totalLeadsValid = 0;
        foreach ($leads as $lead) {
            if (empty($lead->getPoints())) {
                continue;
            }
            $leadPoints += $lead->getPoints();
            ++$totalLeadsValid;
        }

        $resultScore = $leadPoints;
        if (!empty($totalLeadsValid)) {
            $resultScore = $leadPoints / $totalLeadsValid;
        }

        if (fmod($resultScore, 1)) {
            $resultScore = floor($resultScore) + 1;
        }

        $resultScore += $companyScore;

        if ((int) $resultScore !== $currentScore) {
            $this->setFieldValues($company, ['companyscore_calculated' => $resultScore]);
            $this->saveEntity($company);
        }

        return $resultScore;
    }

    /**
     * @return array<Company>
     */
    public function getCompanies(int $limit = 0, int $offset = 0): array
    {
        $result = $this->getEntities([
            'limit' => $limit,
            'start' => $offset,
        ]);

        return is_array($result) ? $result : iterator_to_array($result);
    }

    /**
     * @return array<Lead>
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

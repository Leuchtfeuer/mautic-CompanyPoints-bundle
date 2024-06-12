<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\LeadBundle\Entity\Company;

class CompanyPointLog
{
    public const TABLE_NAME = 'company_point_company_action_log';
    /**
     * @var CompanyPoint
     **/
    private $company_points;


    private $company;

    /**
     * @var IpAddress|null
     */
    private $ipAddress;

    /**
     * @var \DateTimeInterface
     **/
    private $dateFired;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CompanyPointLogRepository::class);

        $builder->createManyToOne('company_points', 'CompanyPoint')
            ->isPrimaryKey()
            ->addJoinColumn('point_id', 'id', true, false, 'CASCADE')
            ->inversedBy('log')
            ->build();

        $builder->createManyToOne('company', Company::class)
            ->isPrimaryKey()
            ->addJoinColumn('company_id', 'id', false, false, 'CASCADE')
            ->inversedBy('log')
            ->build();

        $builder->addIpAddress(true);

        $builder->createField('dateFired', 'datetime')
            ->columnName('date_fired')
            ->build();
    }

    /**
     * @return mixed
     */
    public function getDateFired()
    {
        return $this->dateFired;
    }

    /**
     * @param mixed $dateFired
     */
    public function setDateFired($dateFired): void
    {
        $this->dateFired = $dateFired;
    }

    /**
     * @return IpAddress|null
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * @param IpAddress $ipAddress
     */
    public function setIpAddress($ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * @return mixed
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @param mixed $lead
     */
    public function setLead($company): void
    {
        $this->company = $company;
    }

    /**
     * @return mixed
     */
    public function getPoint()
    {
        return $this->company_points;
    }

    /**
     * @param mixed $point
     */
    public function setPoint($company_points): void
    {
        $this->company_points = $company_points;
    }
}

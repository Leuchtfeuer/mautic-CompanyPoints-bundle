<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\LeadBundle\Entity\Company;
class CompanyTriggerLog
{
    public const TABLE_NAME = 'company_point_company_event_log';
    /**
     * @var CompanyTriggerEvent
     **/
    private $event;

    /**
     * @var Company
     **/
    private $company;

    /**
     * @var IpAddress|null
     **/
    private $ipAddress;

    /**
     * @var \DateTimeInterface
     **/
    private $dateFired;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CompanyTriggerLogRepository::class);

        $builder->createManyToOne('event', 'CompanyTriggerEvent')
            ->isPrimaryKey()
            ->addJoinColumn('event_id', 'id', false, false, 'CASCADE')
            ->inversedBy('log')
            ->build();

//        $builder->addLead(false, 'CASCADE', true);
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
    public function setCompany($company): void
    {
        $this->company = $company;
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param mixed $event
     */
    public function setEvent($event): void
    {
        $this->event = $event;
    }
}

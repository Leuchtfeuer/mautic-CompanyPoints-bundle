<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CompanyTrigger extends FormEntity
{

    public const TYPE_POINTS = 'points';
    public const TYPE_MEMBER_ACTIVITY = 'member_activity';

    public const ACTIVITY_FIRST_EVER          = 'first_contact_activity_ever';
    public const ACTIVITY_FIRST_WITHIN_30_DAYS  = 'first_contact_activity_within_30_days';
    public const ACTIVITY_FIRST_OF_NEW_CONTACT = 'first_activity_of_new_contact';
    public const ACTIVITY_EVERY_OF_A_CONTACT     = 'every_activity_of_a_contact';
    public const ACTIVITY_EVERY_OF_KNOWN_CONTACT = 'every_activity_of_a_known_contact';

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var \DateTimeInterface
     */
    private $publishUp;

    /**
     * @var \DateTimeInterface
     */
    private $publishDown;

    private string $type = '';

    /**
     * @var int
     */
    private $points = 0;

    /**
     * @var string
     */
    private $color = 'a0acb8';

    private ?string $memberActivity = null;

    /**
     * @var array<string, mixed>
     */
    private ?array $companySegmentMembershipFilter = null;

    /**
     * @var bool
     */
    //    private $triggerExistingLeads = false;

    /**
     * @var \Mautic\CategoryBundle\Entity\Category|null
     **/
    private $category;

    /**
     * @var ArrayCollection<int, \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent>
     */
    private $events;

    //    private ?Group $group = null;

    public function __clone()
    {
        $this->id = null;

        parent::__clone();
    }

    public function __construct()
    {
        $this->events = new ArrayCollection();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('company_point_triggers')
            ->setCustomRepositoryClass(CompanyTriggerRepository::class);

        $builder->addIdColumns();

        $builder->addPublishDates();

        $builder->createField('type', 'string')
            ->build();

        $builder->addField('points', 'integer');

        $builder->createField('color', 'string')
            ->length(7)
            ->build();

        $builder->createField('memberActivity', 'string')
            ->columnName('member_activity')
            ->nullable()
            ->build();

        $builder->createField('companySegmentMembershipFilter', 'json')
            ->columnName('company_segment_membership_filter')
            ->nullable()
            ->build();
        //        $builder->createField('triggerExistingLeads', 'boolean')
        //            ->columnName('trigger_existing_leads')
        //            ->build();

        $builder->addCategory();

        $builder->createOneToMany('events', 'CompanyTriggerEvent')
            ->setIndexBy('id')
            ->setOrderBy(['order' => 'ASC'])
            ->mappedBy('trigger')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        //        $builder->createManyToOne('group', Group::class)
        //            ->addJoinColumn('group_id', 'id', true, false, 'CASCADE')
        //            ->build();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank([
            'message' => 'mautic.core.name.required',
        ]));
    }

    /**
     * Prepares the metadata for API usage.
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('trigger')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                    'description',
                ]
            )
            ->addProperties(
                [
                    'publishUp',
                    'publishDown',
                    'type',
                    'points',
                    'color',
                    'memberActivity',
                    'events',
                    //                    'triggerExistingLeads',
                ]
            )
            ->build();
    }

    /**
     * @param string $prop
     * @param mixed  $val
     */
    protected function isChanged($prop, $val)
    {
        if ('events' == $prop) {
            // changes are already computed so just add them
            $this->changes[$prop][$val[0]] = $val[1];
        } else {
            parent::isChanged($prop, $val);
        }
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Trigger
     */
    public function setDescription($description)
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Trigger
     */
    public function setName($name)
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add events.
     *
     * @return Point
     */
    public function addTriggerEvent($key, CompanyTriggerEvent $event)
    {
        if ($changes = $event->getChanges()) {
            $this->isChanged('events', [$key, $changes]);
        }
        $this->events[$key] = $event;

        return $this;
    }

    /**
     * Remove events.
     */
    public function removeTriggerEvent(CompanyTriggerEvent $event): void
    {
        $this->events->removeElement($event);
    }

    /**
     * Get events.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Set publishUp.
     *
     * @param \DateTime $publishUp
     *
     * @return Point
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * Get publishUp.
     *
     * @return \DateTimeInterface
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * Set publishDown.
     *
     * @param \DateTime $publishDown
     *
     * @return Point
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * Get publishDown.
     *
     * @return \DateTimeInterface
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->isChanged('type', $type);
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @param mixed $points
     */
    public function setPoints($points): void
    {
        $this->isChanged('points', $points);
        $this->points = $points;
    }

    /**
     * @return mixed
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param mixed $color
     */
    public function setColor($color): void
    {
        $this->color = $color;
    }

    public function getMemberActivity(): ?string
    {
        return $this->memberActivity;
    }

    public function setMemberActivity(?string $memberActivity): void
    {
        $this->isChanged('memberActivity', $memberActivity);
        $this->memberActivity = $memberActivity;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCompanySegmentMembershipFilter(): ?array
    {
        return $this->companySegmentMembershipFilter;
    }

    /**
     * @param array<string, mixed> $companySegmentMembershipFilter
     */
    public function setCompanySegmentMembershipFilter(array $companySegmentMembershipFilter): void
    {
        if (!isset($companySegmentMembershipFilter['operator'])
            || !in_array($companySegmentMembershipFilter['operator'], ['in', 'notIn'], true)) {
            return;
        }

        if (!isset($companySegmentMembershipFilter['segments'])
            || !is_array($companySegmentMembershipFilter['segments'])
            || count($companySegmentMembershipFilter['segments']) < 1) {
            return;
        }

        $this->isChanged('companySegmentMembershipFilter', $companySegmentMembershipFilter);
        $this->companySegmentMembershipFilter = $companySegmentMembershipFilter;
    }

    //    /**
    //     * @return mixed
    //     */
    //    public function getTriggerExistingLeads()
    //    {
    //        return $this->triggerExistingLeads;
    //    }

    //    /**
    //     * @param mixed $triggerExistingLeads
    //     */
    //    public function setTriggerExistingLeads($triggerExistingLeads): void
    //    {
    //        $this->triggerExistingLeads = $triggerExistingLeads;
    //    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     */
    public function setCategory($category): void
    {
        $this->category = $category;
    }

    //    public function getGroup(): ?Group
    //    {
    //        return $this->group;
    //    }
    //
    //    public function setGroup(Group $group): void
    //    {
    //        $this->group = $group;
    //    }
}

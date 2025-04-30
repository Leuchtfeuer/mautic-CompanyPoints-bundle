<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel as CommonFormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerLog;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event as Events;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanyTriggerType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents as CompanyPointEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CompanyTriggerModel extends CommonFormModel
{
    protected $triggers = [];

    /**
     * @var array<string,array<string,mixed>>
     */
    private static array $events;

    public function __construct(
        protected IpLookupHelper $ipLookupHelper,
        protected LeadModel $leadModel,
        protected CompanyTriggerEventModel $pointTriggerEventModel,
        /**
         * @deprecated https://github.com/mautic/mautic/issues/8229
         */
        protected MauticFactory $mauticFactory,
        private ContactTracker $contactTracker,
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper);
    }

    /**
     * @return \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository(CompanyTrigger::class);
    }

    /**
     * Retrieves an instance of the TriggerEventRepository.
     *
     * @return \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository
     */
    public function getEventRepository()
    {
        return $this->em->getRepository(CompanyTriggerEvent::class);
    }

    public function getEventTriggerLogRepository()
    {
        return $this->em->getRepository(CompanyTriggerLog::class);
    }

    public function getPermissionBase(): string
    {
        return 'companypoint:triggers';
    }

    /**
     * @throws MethodNotAllowedHttpException
     */
    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): \Symfony\Component\Form\FormInterface
    {
        if (!$entity instanceof CompanyTrigger) {
            throw new MethodNotAllowedHttpException(['CompanyTrigger']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(CompanyTriggerType::class, $entity, $options);
    }

    /**
     * @param CompanyTrigger $entity
     * @param bool           $unlock
     */
    public function saveEntity($entity, $unlock = true): void
    {
        $isNew = ($entity->getId()) ? false : true;

        parent::saveEntity($entity, $unlock);

        // should we trigger for existing leads?
        if ($entity->isPublished()) {
            $events      = $entity->getEvents();
            $repo        = $this->getEventRepository();
            $persist     = [];
            $ipAddress   = $this->ipLookupHelper->getIpAddress();
            //            $pointGroup  = $entity->getGroup();

            /** @var LeadRepository $leadRepository */
            $leadRepository = $this->em->getRepository(Lead::class);

            foreach ($events as $event) {
                $args = [
                    'filter' => [
                        'force' => [
                            [
                                'column' => 'l.date_added',
                                'expr'   => 'lte',
                                'value'  => (new DateTimeHelper($entity->getDateAdded()))->toUtcString(),
                            ],
                        ],
                    ],
                ];

                //                if (!$pointGroup) {
                //                    $args['filter']['force'][] = [
                //                        'column' => 'l.points',
                //                        'expr'   => 'gte',
                //                        'value'  => $entity->getPoints(),
                //                    ];
                //                } else {
                //                $args['qb'] = $leadRepository->getEntitiesDbalQueryBuilder()
                //                    ->leftJoin('l', MAUTIC_TABLE_PREFIX.GroupContactScore::TABLE_NAME, 'pls', 'l.id = pls.contact_id');
                //                $args['filter']['force'][] = [
                //                    'column' => 'pls.score',
                //                    'expr'   => 'gte',
                //                    'value'  => $entity->getPoints(),
                //                ];
                //                $args['filter']['force'][] = [
                //                    'column' => 'pls.group_id',
                //                    'expr'   => 'eq',
                //                    'value'  => $entity->getGroup()->getId(),
                //                ];
                //                }

                if (!$isNew) {
                    // get a list of leads that has already had this event applied
                    //                    $leadIds = $repo->getLeadsForEvent($event->getId());
                    $companyIds = $repo->getCompaniesForEvent($event->getId());
                    if (!empty($companyIds)) {
                        $args['filter']['force'][] = [
                            'column' => 'l.id',
                            'expr'   => 'notIn',
                            'value'  => $companyIds,
                        ];
                    }
                }

                // get a list of leads that are before the trigger's date_added and trigger if not already done so
                $companies = $this->leadModel->getEntities($args);

                /** @var Lead $l */
                foreach ($companies as $l) {
                    if ($this->triggerEvent($event->convertToArray(), $l, true)) {
                        $log = new CompanyTriggerLog();
                        $log->setIpAddress($ipAddress);
                        $log->setEvent($event);
                        $log->setCompany($l);
                        $log->setDateFired(new \DateTime());
                        $event->addLog($log);
                        $persist[] = $event;
                    }
                }
            }

            if (!empty($persist)) {
                $repo->saveEntities($persist);
            }
        }
    }

    public function getEntity($id = null): ?CompanyTrigger
    {
        if (null === $id) {
            return new CompanyTrigger();
        }

        return parent::getEntity($id);
    }

    /**
     * @throws MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null): ?Event
    {
        if (!$entity instanceof CompanyTrigger) {
            throw new MethodNotAllowedHttpException(['CompanyTrigger']);
        }

        switch ($action) {
            case 'pre_save':
                $name = CompanyPointEvents::COMPANY_TRIGGER_PRE_SAVE;
                break;
            case 'post_save':
                $name = CompanyPointEvents::COMPANY_TRIGGER_POST_SAVE;
                break;
            case 'pre_delete':
                $name = CompanyPointEvents::COMPANY_TRIGGER_PRE_DELETE;
                break;
            case 'post_delete':
                $name = CompanyPointEvents::COMPANY_TRIGGER_POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new Events\CompanyTriggerEvent($entity, $isNew);
            }

            $this->dispatcher->dispatch($event, $name);

            return $event;
        }

        return null;
    }

    /**
     * @param array $sessionEvents
     */
    public function setEvents(CompanyTrigger $entity, $sessionEvents): void
    {
        $order           = 1;
        $existingActions = $entity->getEvents();

        foreach ($sessionEvents as $properties) {
            $isNew = (!empty($properties['id']) && isset($existingActions[$properties['id']])) ? false : true;
            $event = !$isNew ? $existingActions[$properties['id']] : new CompanyTriggerEvent();

            foreach ($properties as $f => $v) {
                if (in_array($f, ['id', 'order'])) {
                    continue;
                }

                $func = 'set'.ucfirst($f);
                if (method_exists($event, $func)) {
                    $event->$func($v);
                }
            }
            $event->setTrigger($entity);
            $event->setOrder($order);
            ++$order;
            $entity->addTriggerEvent($properties['id'], $event);
        }

        // Persist if editing the trigger
        if ($entity->getId()) {
            $this->pointTriggerEventModel->saveEntities($entity->getEvents());
        }
    }

    /**
     * Gets array of custom events from bundles subscribed PointEvents::TRIGGER_ON_BUILD.
     *
     * @return mixed[]
     */
    public function getEvents()
    {
        if (empty(self::$events)) {
            // build them
            self::$events = [];
            //            $event        = new Events\CompanyTriggerBuilderEvent($this->translator);
            $event        = new CompanyTriggerBuilderEvent($this->translator);
            $this->dispatcher->dispatch($event, CompanyPointEvents::COMPANY_TRIGGER_ON_BUILD);
            self::$events = $event->getEvents();
        }

        return self::$events;
    }

    /**
     * Gets array of custom events from bundles inside groups.
     *
     * @return mixed[]
     */
    public function getEventGroups(): array
    {
        $events = $this->getEvents();
        $groups = [];
        foreach ($events as $key => $event) {
            $groups[$event['group']][$key] = $event;
        }

        return $groups;
    }

    /**
     * Triggers a specific event.
     *
     * @param array $event triggerEvent converted to array
     * @param bool  $force
     *
     * @return bool Was event triggered
     */
    public function triggerEvent($event, Lead $lead = null, $force = false)
    {
        // only trigger events for anonymous users
        if (!$force && !$this->security->isAnonymous()) {
            return false;
        }

        if (null === $lead) {
            $lead = $this->contactTracker->getContact();
        }

        if (!$force) {
            // get a list of events that has already been performed on this lead
            $appliedEvents = $this->getEventRepository()->getLeadTriggeredEvents($lead->getId());

            // if it's already been done, then skip it
            if (isset($appliedEvents[$event['id']])) {
                return false;
            }
        }

        $availableEvents = $this->getEvents();
        $eventType       = $event['type'];

        // make sure the event still exists
        if (!isset($availableEvents[$eventType])) {
            return false;
        }

        $settings = $availableEvents[$eventType];

        if (isset($settings['callback']) && is_callable($settings['callback'])) {
            return $this->invokeCallback($event, $lead, $settings);
        } else {
            /** @var CompanyTriggerEvent $triggerEvent */
            $triggerEvent = $this->getEventRepository()->find($event['id']);

            $triggerExecutedEvent = new Events\CompanyTriggerExecutedEvent($triggerEvent, $lead);

            $this->dispatcher->dispatch($triggerExecutedEvent, $settings['eventName']);

            return $triggerExecutedEvent->getResult();
        }
    }

    /**
     * @return bool
     */
    private function invokeCallback($event, Lead $lead, array $settings)
    {
        $args = [
            'event'   => $event,
            'lead'    => $lead,
            'factory' => $this->mauticFactory,
            'config'  => $event['properties'],
        ];

        if (is_array($settings['callback'])) {
            $reflection = new \ReflectionMethod($settings['callback'][0], $settings['callback'][1]);
        } elseif (str_contains($settings['callback'], '::')) {
            $parts      = explode('::', $settings['callback']);
            $reflection = new \ReflectionMethod($parts[0], $parts[1]);
        } else {
            $reflection = new \ReflectionMethod(null, $settings['callback']);
        }

        $pass = [];
        foreach ($reflection->getParameters() as $param) {
            if (isset($args[$param->getName()])) {
                $pass[] = $args[$param->getName()];
            } else {
                $pass[] = null;
            }
        }

        return $reflection->invokeArgs($this, $pass);
    }

    /**
     * Trigger events for the current lead.
     */
    public function triggerEvents(Lead $lead): void
    {
        $points = $lead->getPoints();

        // find all published triggers that is applicable to this points
        //        /** @var \Mautic\PointBundle\Entity\TriggerEventRepository $repo */
        /** @var \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository $repo */
        $repo         = $this->getEventRepository();
        $events       = $repo->getPublishedByPointTotal($points);
        $groupEvents  = $repo->getPublishedByGroupScore($lead->getGroupScores());
        $events       = array_merge($events, $groupEvents);

        if (!empty($events)) {
            // get a list of actions that has already been applied to this lead
            $appliedEvents = $repo->getLeadTriggeredEvents($lead->getId());
            $ipAddress     = $this->ipLookupHelper->getIpAddress();
            $persist       = [];

            foreach ($events as $event) {
                if (isset($appliedEvents[$event['id']])) {
                    // don't apply the event to the lead if it's already been done
                    continue;
                }

                if ($this->triggerEvent($event, $lead, true)) {
                    $log = new CompanyTriggerLog();
                    $log->setIpAddress($ipAddress);
                    $log->setEvent($triggerEvent = $this->getEventRepository()->find($event['id']));
                    $log->setLead($lead);
                    $log->setDateFired(new \DateTime());
                    $persist[] = $log;
                }
            }

            if (!empty($persist)) {
                $this->getEventRepository()->saveEntities($persist);
                $this->getEventRepository()->detachEntities($persist);
                if (isset($triggerEvent)) {
                    $this->getEventRepository()->deleteEntity($triggerEvent);
                }
            }
        }
    }

    /**
     * Returns configured color based on passed in $points.
     *
     * @return string
     */
    public function getColorForLeadPoints($points)
    {
        if (!$this->triggers) {
            $this->triggers = $this->getRepository()->getTriggerColors();
        }

        foreach ($this->triggers as $trigger) {
            if ($points >= $trigger['points']) {
                return $trigger['color'];
            }
        }

        return '';
    }

    public function saveLog($company, $event): void
    {
        $companyEventLog = new CompanyTriggerLog();
        $companyEventLog->setCompany($company);
        $companyEventLog->setEvent($event);
        $companyEventLog->setIpAddress($this->ipLookupHelper->getIpAddress());
        $companyEventLog->setDateFired(new \DateTime());
        $this->getEventTriggerLogRepository()->saveEntity($companyEventLog);
    }
}

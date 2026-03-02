<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel as CommonFormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerLog;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event as Events;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanyTriggerType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents as CompanyPointEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
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
        private ContactTracker $contactTracker,
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper,
        private EmailModel $emailModel,
        private MailHelper $mailHelper,
        private CompanyTagModel $companyTagModel,
        private CompanySegmentModel $companySegmentModel,
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
    public function dispatchEvent($action, &$entity, $isNew = false, Event $event = null): ?Event
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

        /** @var CompanyTriggerEvent $triggerEvent */
        $triggerEvent = $this->getEventRepository()->find($event['id']);

        $triggerExecutedEvent = new Events\CompanyTriggerExecutedEvent($triggerEvent, $lead);

        $this->dispatcher->dispatch($triggerExecutedEvent, $settings['eventName']);

        return $triggerExecutedEvent->getResult();
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

    public function sendEmails(array $users, array $properties, ?User $owner, Company $company): array
    {
        $result = [];
        if (empty($users) || empty($properties['email'])) {
            return $result;
        }

        foreach ($users as $user) {
            assert($user instanceof User);
            $result[$user->getId()] = $this->sendEmail($properties['email'], $user, $owner, $company);
        }

        return $result;
    }

    public function sendEmail($emailId, User $user, ?User $owner, Company $company): bool|array
    {
        $email = $this->emailModel->getRepository()->find($emailId);
        if (!$email || !$email->isPublished()) {
            return false;
        }
        $this->mailHelper->setEmail($email);
        if (!empty($user->getEmail())) {
            $this->mailHelper->addTo($user->getEmail());
        }
        if (!empty($properties['to'])) {
            $this->mailHelper->addTo($properties['to']);
        }
        if (!empty($properties['email_to_owner']) && !empty($owner)) {
            $this->mailHelper->addTo($owner->getEmail());
        }
        if (!empty($properties['cc'])) {
            $this->mailHelper->addCc($properties['cc']);
        }
        if (!empty($properties['bcc'])) {
            $this->mailHelper->addBcc($properties['bcc']);
        }

        $tokens = $this->mailHelper->getTokens();
        $this->mailHelper->addTokens($tokens);
        $newTokens = $this->getTokens($company);
        $this->mailHelper->addTokens($newTokens);
        $result = $this->mailHelper->send();
        $this->mailHelper->reset();

        return $result;
    }

    private function getTokens(Company $company): array
    {
        $fields = $company->getFields();

        $fullFields = array_merge(
            $fields['professional'] ?? [],
            $fields['personal'] ?? [],
            $fields['custom'] ?? [],
            $fields['core'] ?? [],
        );
        $tokensFields = [];
        foreach ($fullFields as $key => $field) {
            $tempKey = $key;
            if ('companyscore_calculated' === $key) {
                $tempKey = 'companies.companyscore_calculated';
            }
            $keyToken                = '{contactfield='.$tempKey.'}';
            $tokensFields[$keyToken] = '';
            if (isset($field['value']) && !empty($field['value'])) {
                $tokensFields[$keyToken] = $field['value'];
                if ('companyscore_calculated' === $key) {
                    $tokensFields['{contactfield='.$key.'}'] = $field['value'];
                }
            }
        }

        $companyTagsString     = $this->getCompanyTagsString($company);
        $companySegmentsString = $this->getCompanySegmentsString($company);

        $tokensFields['{contactfield=points}']            = $company->getScore();
        $tokensFields['{contactfield=companies.points}']  = $company->getScore();
        $tokensFields['{companylist=tags}']               = $companyTagsString;
        $tokensFields['{companylist=segments}']           = $companySegmentsString;

        return $tokensFields;
    }

    private function getCompanySegmentsString(Company $company): string
    {
        $companySegments       = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(['company' => $company]);
        $companySegmentsString = [];
        foreach ($companySegments as $companySegment) {
            $companySegmentsString[]= $companySegment->getCompanySegment()->getName();
        }

        return implode(', ', $companySegmentsString);
    }

    private function getCompanyTagsString(Company $company): string
    {
        $companyTags       = $this->companyTagModel->getTagsByCompany($company);
        $companyTagsString = [];
        foreach ($companyTags as $companyTag) {
            $companyTagsString[]= $companyTag->getName();
        }

        return implode(', ', $companyTagsString);
    }
}

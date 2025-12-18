<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\LeadChangeCompanyEvent;
use Mautic\LeadBundle\Event\LeadMergeEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\BeforeUpdateLeadActivityEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\CompanyMemberActivityService;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\LeadCompanyResolver;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\MergeActivityTracker;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyCampaignsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyTagsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\SendEmailActionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MemberActivityTriggerSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY_MODIFY_TAGS       = 'companytags.updatetags';
    public const TRIGGER_KEY_SEND_EMAIL        = 'companytags.sendemails';
    public const TRIGGER_KEY_MODIFY_CAMPAIGNS  = 'companypoints.modifycampaigns';

    /**
     * @var array<string, object>
     */
    private array $handlers = [];

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private CompanyTriggerEventRepository $companyTriggerEventRepository,
        private LeadCompanyResolver $leadCompanyResolver,
        private CompanyMemberActivityService $companyMemberActivityService,
        private MergeActivityTracker $mergeActivityTracker,
        private Config $pluginConfig,
        ModifyTagsActionHandler $modifyTagsActionHandler,
        SendEmailActionHandler $sendEmailActionHandler,
        ModifyCampaignsActionHandler $modifyCampaignsActionHandler
    ) {
        $this->handlers = [
            self::TRIGGER_KEY_MODIFY_TAGS       => $modifyTagsActionHandler,
            self::TRIGGER_KEY_SEND_EMAIL        => $sendEmailActionHandler,
            self::TRIGGER_KEY_MODIFY_CAMPAIGNS  => $modifyCampaignsActionHandler,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Track merges where the loser lead had no previous activity
            LeadEvents::LEAD_PRE_MERGE           => ['onLeadPreMerge', 0],
            // This handles activity from contacts ALREADY in a company
            BeforeUpdateLeadActivityEvent::class => ['onLeadActivity', 0],
            // This handles the moment a contact is ADDED to a company
            LeadEvents::LEAD_COMPANY_CHANGE      => ['onCompanyChange', 0],
            FormEvents::FORM_ON_SUBMIT           => ['onFormSubmit', 0],
        ];
    }

    /**
     * Triggered when an existing company member becomes active.
     */
    public function onLeadActivity(BeforeUpdateLeadActivityEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $lead = $event->lead;

        $primaryCompany = $this->leadCompanyResolver->getPrimaryCompanyByLead($lead);
        if (null === $primaryCompany) {
            return;
        }

        $this->processCompanyTriggers($lead, $primaryCompany);
    }

    public function onLeadPreMerge(LeadMergeEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $winner = $event->getVictor();
        $loser  = $event->getLoser();

        if ($winner->isAnonymous() && null === $loser->getLastActive()) {
            // Track this contact as receiving first activity through merge
            $this->mergeActivityTracker->trackFirstActivityAfterMerge($winner->getId());
        }
    }

    /**
     * Triggered when a lead is added to a company.
     */
    public function onCompanyChange(LeadChangeCompanyEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        if (
            !$event->wasAdded()
            || !$this->companyMemberActivityService->isJoinCoincidingWithActivity($event->getLead(), $event->getCompany())
        ) {
            return;
        }
        $this->processCompanyTriggers($event->getLead(), $event->getCompany(), true);
    }

    public function onFormSubmit(SubmissionEvent $submissionEvent): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $lead           = $submissionEvent->getLead();
        if (null === $lead) {
            return;
        }
        $primaryCompany = $this->leadCompanyResolver->getPrimaryCompanyByLead($lead);
        if (null === $primaryCompany) {
            return;
        }

        $this->processCompanyTriggers($lead, $primaryCompany, true);
    }

    /**
     * Centralized logic to check and execute triggers for a given lead/company pair.
     */
    private function processCompanyTriggers(Lead $lead, Company $company, bool $isActivityAlreadyCounted = false): void
    {
        $allTriggers = $this->companyTriggerEventRepository->getPublishedByTriggerType(CompanyTrigger::TYPE_MEMBER_ACTIVITY);
        if (empty($allTriggers)) {
            return;
        }

        $loggedEventIds = $this->getLoggedEventIdsForCompany($company);

        /** @var CompanyTriggerEvent $eventTrigger */
        foreach ($allTriggers as $eventTrigger) {
            if ($this->shouldExecute($eventTrigger, $lead, $company, $loggedEventIds, $isActivityAlreadyCounted)) {
                $handler = $this->getHandlerForTrigger($eventTrigger);

                if (null !== $handler) {
                    $handler->execute($company, $eventTrigger->getProperties());
                    $this->companyTriggerModel->saveLog($company, $eventTrigger);
                }
            }
        }
    }

    private function shouldExecute(CompanyTriggerEvent $eventTrigger, Lead $lead, Company $company, array $loggedIds, bool $isActivityAlreadyCounted): bool
    {
        if (!isset($this->handlers[$eventTrigger->getType()]) || in_array($eventTrigger->getId(), $loggedIds, true)) {
            return false;
        }

        $trigger = $eventTrigger->getTrigger();
        if (null === $trigger || CompanyTrigger::TYPE_MEMBER_ACTIVITY !== $trigger->getType()) {
            return false;
        }

        $memberActivityTrigger = $trigger->getMemberActivity();
        if (null === $memberActivityTrigger) {
            return false;
        }

        switch ($memberActivityTrigger) {
            case CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT:
                return true;

            case CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT:
                return !$this->companyMemberActivityService->isAnonymousLead($lead);

            case CompanyTrigger::ACTIVITY_FIRST_EVER:
                if ($isActivityAlreadyCounted || $this->companyMemberActivityService->isLeadFirstActivity($lead, $company)) {
                    $activityCount = $this->companyMemberActivityService->countLeadActivities($company, excludeLead: $lead);
                } else {
                    $activityCount = $this->companyMemberActivityService->countLeadActivities($company);
                }

                return 0 === $activityCount;

            case CompanyTrigger::ACTIVITY_FIRST_WITHIN_30_DAYS:
                if ($isActivityAlreadyCounted || $this->companyMemberActivityService->isLeadFirstActivity($lead, $company)) {
                    $activityCount = $this->companyMemberActivityService->countLeadActivities($company, 30, excludeLead: $lead);
                } else {
                    $activityCount = $this->companyMemberActivityService->countLeadActivities($company, 30);
                }

                return 0 === $activityCount;

            case CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT:
                return $this->companyMemberActivityService->isLeadFirstActivity($lead, $company);

            default:
                return false;
        }
    }

    /**
     * @return int[]
     */
    private function getLoggedEventIdsForCompany(Company $company): array
    {
        $eventLogged    = $this->companyTriggerModel->getEventTriggerLogRepository()->findBy(['company' => $company]);
        $eventLoggedIds = [];
        foreach ($eventLogged as $eventLog) {
            $eventLoggedIds[] = $eventLog->getEvent()->getId();
        }

        return $eventLoggedIds;
    }

    private function getHandlerForTrigger(CompanyTriggerEvent $eventTrigger): ?object
    {
        return $this->handlers[$eventTrigger->getType()] ?? null;
    }
}

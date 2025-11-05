<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyScoreModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyTagsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\SendEmailActionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MemberActivityTriggerSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY_MODIFY_TAGS = 'companytags.updatetags';
    public const TRIGGER_KEY_SEND_EMAIL  = 'companytags.sendemails';

    /**
     * @var array<string, object>
     */
    private array $handlers = [];

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private CompanyTriggerEventRepository $companyTriggerEventRepository,
        private CompanyScoreModel $companyScoreModel,
        ModifyTagsActionHandler $modifyTagsActionHandler,
        SendEmailActionHandler $sendEmailActionHandler
    ) {
        // Map the trigger keys to their corresponding handlers.
        $this->handlers = [
            self::TRIGGER_KEY_MODIFY_TAGS => $modifyTagsActionHandler,
            self::TRIGGER_KEY_SEND_EMAIL  => $sendEmailActionHandler,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageEvents::PAGE_ON_HIT => ['onPageHit', 0],
        ];
    }

    public function onPageHit(PageHitEvent $event): void
    {
        $hit = $event->getHit();
        $lead = $hit->getLead();

        if (null === $lead) {
            return;
        }

        $leadCompanies = $this->companyScoreModel->getCompaniesByLead($lead);
        if (empty($leadCompanies)) {
            return;
        }

        $allTriggers = $this->companyTriggerEventRepository->getPublishedByTriggerType(CompanyTrigger::TYPE_MEMBER_ACTIVITY);
        if (empty($allTriggers)) {
            return;
        }

        foreach ($leadCompanies as $company) {
            $loggedEventIds = $this->getLoggedEventIdsForCompany($company);

            /** @var CompanyTriggerEvent $eventTrigger */
            foreach ($allTriggers as $eventTrigger) {
                if ($this->shouldExecute($eventTrigger, $lead, $company, $loggedEventIds)) {
                    $handler = $this->getHandlerForTrigger($eventTrigger);

                    if (null !== $handler) {
                        $handler->execute($company, $eventTrigger->getProperties());
                        $this->companyTriggerModel->saveLog($company, $eventTrigger);
                    }
                }
            }
        }
    }


    /**
     * @param int[] $loggedIds
     */
    private function shouldExecute(CompanyTriggerEvent $eventTrigger, Lead $lead, Company $company, array $loggedIds): bool
    {
        // Check if the trigger type has a registered handler.
        if (!isset($this->handlers[$eventTrigger->getType()])) {
            return false;
        }

        // Check if this trigger has already been executed for this company
        if (in_array($eventTrigger->getId(), $loggedIds, true)) {
            return false;
        }

        $trigger = $eventTrigger->getTrigger();
        // Check if the trigger is a member_activity-based trigger
        if (null === $trigger || $trigger->getType() !== CompanyTrigger::TYPE_MEMBER_ACTIVITY) {
            return false;
        }

        $memberActivityTrigger = $trigger->getMemberActivity();

        /**
         * TODO check for every type:
         * CompanyTrigger::ACTIVITY_FIRST_EVER                // "First contact activity in this company ever"
         * CompanyTrigger::ACTIVITY_FIRST_WITHIN_30_DAYS      // "First contact activity in this company within 30 days"
         * CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT      // "First activity of every new contact"
         * CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT        // "Every activity of a contact"
         * CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT    // "Every activity of a known contact"
         */

        return false;
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
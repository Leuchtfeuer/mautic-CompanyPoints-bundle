<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPostRecalculateEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CompanySegmentHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyCampaignsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyCompanySegmentsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyTagsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\SendEmailActionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PointTriggerSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY_MODIFY_TAGS              = 'companytags.updatetags';
    public const TRIGGER_KEY_SEND_EMAIL               = 'companytags.sendemails';
    public const TRIGGER_KEY_MODIFY_CAMPAIGNS         = 'companypoints.modifycampaigns';
    public const TRIGGER_KEY_MODIFY_COMPANY_SEGMENTS  = 'companypoints.modifycompanysegments';

    /**
     * @var array<string, object>
     */
    private array $handlers = [];

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private CompanyTriggerEventRepository $companyTriggerEventRepository,
        private Config $pluginConfig,
        private CompanySegmentHelper $companySegmentHelper,
        ModifyTagsActionHandler $modifyTagsActionHandler,
        SendEmailActionHandler $sendEmailActionHandler,
        ModifyCampaignsActionHandler $modifyCampaignsActionHandler,
        ModifyCompanySegmentsActionHandler $modifyCompanySegmentsActionHandler,
    ) {
        // Map the trigger keys to their corresponding handlers.
        $this->handlers = [
            self::TRIGGER_KEY_MODIFY_TAGS             => $modifyTagsActionHandler,
            self::TRIGGER_KEY_SEND_EMAIL              => $sendEmailActionHandler,
            self::TRIGGER_KEY_MODIFY_CAMPAIGNS        => $modifyCampaignsActionHandler,
            self::TRIGGER_KEY_MODIFY_COMPANY_SEGMENTS => $modifyCompanySegmentsActionHandler,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE => ['onPointRecalculate', 0],
        ];
    }

    public function onPointRecalculate(CompanyPostRecalculateEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $company = $event->getCompany();

        // Fetch all trigger events associated with triggers of TYPE_POINTS.
        $allTriggers = $this->companyTriggerEventRepository->getPublishedByTriggerType(CompanyTrigger::TYPE_POINTS);

        if (empty($allTriggers)) {
            return;
        }

        $loggedEventIds = $this->getLoggedEventIdsForCompany($company);

        /** @var CompanyTriggerEvent $eventTrigger */
        foreach ($allTriggers as $eventTrigger) {
            if ($this->shouldExecute($eventTrigger, $company, $loggedEventIds)) {
                $handler = $this->getHandlerForTrigger($eventTrigger);

                if (null !== $handler) {
                    $handler->execute($company, $eventTrigger->getProperties());
                    $this->companyTriggerModel->saveLog($company, $eventTrigger);
                }
            }
        }
    }

    /**
     * @param int[] $loggedIds
     */
    private function shouldExecute(CompanyTriggerEvent $eventTrigger, Company $company, array $loggedIds): bool
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
        // Check if the trigger is a point-based trigger
        if (null === $trigger || CompanyTrigger::TYPE_POINTS !== $trigger->getType()) {
            return false;
        }

        // Check if the company has reached the required score
        $companyScore = $company->getField('companyscore_calculated')['value'] ?? 0;

        if ($companyScore < $trigger->getPoints()) {
            return false;
        }

        $companySegmentMembershipFilter = $trigger->getCompanySegmentMembershipFilter();
        $hasCorrectSegmentMembership    = $this->companySegmentHelper->companyHasCorrectSegmentMembership($company, $companySegmentMembershipFilter);

        return $hasCorrectSegmentMembership;
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

<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPointBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\ModifyTagsActionHandler;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanyTagsSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.updatetags';

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private ModifyTagsActionHandler $modifyTagsActionHandler
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_UPDATE         => ['onPointExecute', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_SAVE           => ['onPointExecute', 0],
            LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE => ['onPointExecute', 0],
        ];
    }

    public function onPointBuild(CompanyPointBuilderEvent $event): void
    {
        $action = [
            'group'       => 'mautic.companytags.actions',
            'label'       => 'mautic.companytag.companytags.events.changetags',
            'formType'    => ModifyCompanyTagsType::class,
            'description' => 'mautic.ompanytag.companytags.events.changetags_descr',
            'eventName'   => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD,
        ];
        $event->addAction(self::TRIGGER_KEY, $action);
    }

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group'           => 'mautic.companypoints.companytags.group.actions',
            'label'           => 'mautic.companypoints.companytags.group.actions.tag',
            'eventName'       => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
            'formType'        => ModifyCompanyTagsType::class,
            'formTheme'       => '@MauticEmail/FormTheme/EmailSendList/emailsend_list_row.html.twig',
        ];
        $event->addEvent(self::TRIGGER_KEY, $newEvent);
    }

    public function onPointExecute(CompanyTagsEvent $event): void
    {
        $eventTriggers = $this->companyTriggerModel->getEventRepository()->getPublishedByType(self::TRIGGER_KEY);
        if (empty($eventTriggers)) {
            return;
        }

        $company = $event->getCompany();

        $eventLogged = $this->companyTriggerModel->getEventTriggerLogRepository()->findBy(['company' => $company]);
        $eventLoggedIds = [];
        foreach ($eventLogged as $eventLog) {
            $eventLoggedIds[] = $eventLog->getEvent()->getId();
        }

        /** @var CompanyTriggerEvent $eventTrigger */
        foreach ($eventTriggers as $eventTrigger) {
            // Check if this trigger has already been executed for this company
            if (in_array($eventTrigger->getId(), $eventLoggedIds, true)) {
                continue;
            }

            $trigger = $eventTrigger->getTrigger();

            // Check if the trigger is a point-based trigger
            if ($trigger->getType() !== CompanyTrigger::TYPE_POINTS) {
                continue;
            }

            // Ensure company score is initialized for comparison
            $companyScore = $company->getField('companyscore_calculated')['value'] ?? 0;

            // Check if the company has reached the required score
            if ($trigger->getPoints() > $companyScore) {
                continue;
            }

            // --- Decision logic passed. Now execute the action using the handler. ---
            $this->modifyTagsActionHandler->execute(
                $company,
                $eventTrigger->getProperties()
            );

            // After execution, log that this trigger has been processed for the company.
            $this->companyTriggerModel->saveLog(
                $company,
                $eventTrigger
            );
        }
    }
}
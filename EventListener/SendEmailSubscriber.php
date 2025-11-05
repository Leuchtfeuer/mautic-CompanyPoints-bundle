<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanySubmitActionEmailType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service\SendEmailActionHandler;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendEmailSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.sendemails';

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private SendEmailActionHandler $sendEmailActionHandler
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

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group'              => 'mautic.companypoints.sendemail.group.actions',
            'label'              => 'mautic.companypoints.sendemail.group.actions.sendemail',
            'formType'           => CompanySubmitActionEmailType::class,
            'formTypeCleanMasks' => [
                'message' => 'raw',
            ],
            'formTheme' => '@LeuchtfeuerCompanyPoints/FormTheme/FormAction/_formaction_properties_row.html.twig',
            'eventName' => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
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

            // Check if the company has reached the required score
            $companyScore = $company->getField('companyscore_calculated')['value'] ?? 0;
            if ($trigger->getPoints() > $companyScore) {
                continue;
            }

            // --- Decision logic passed. Execute the action using the handler. ---
            $this->sendEmailActionHandler->execute(
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
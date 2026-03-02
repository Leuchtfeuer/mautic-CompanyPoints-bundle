<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanySubmitActionEmailType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendEmailSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.sendemails';

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
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
}

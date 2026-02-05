<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentActionType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanySegmentsSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY  = 'companypoints.modifycompanysegments';

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
        ];
    }

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group'              => 'mautic.companypoints.modifycompanysegment.group.actions',
            'label'              => 'mautic.companypoints.modifycompanysegment.group.actions.modifycompanysegment',
            'formType'           => CompanySegmentActionType::class,
            'eventName'          => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
        ];

        $event->addEvent(self::TRIGGER_KEY, $newEvent);
    }
}

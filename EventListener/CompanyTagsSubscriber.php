<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPointBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanyTagsSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.updatetags';

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
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
}

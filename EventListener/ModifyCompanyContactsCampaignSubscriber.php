<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\CampaignBundle\Form\Type\CampaignEventAddRemoveLeadType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\ModifyCompanyContactsCampaignType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ModifyCompanyContactsCampaignSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companypoints.modifycampaigns';

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
        ];
    }

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group'              => 'mautic.companypoints.modifycampaigns.group.actions',
            'label'              => 'mautic.companypoints.modifycampaigns.group.actions.modifycampaigns',
            'formType'           => ModifyCompanyContactsCampaignType::class,
            'eventName' => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
        ];

        $event->addEvent(self::TRIGGER_KEY, $newEvent);
    }
}
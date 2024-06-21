<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPointBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;

class CompanyTagsSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.updatetags';

    public function __construct(
        private CompanyTagModel $companyTagModel,
        private CompanyTriggerModel $companyTriggerModel,
        private CompanyModel $companyModel,
        private IpLookupHelper $ipLookupHelper,
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
            'group'           => 'mautic.email.point.trigger',
            'label'           => 'mautic.companytag.companytags.events.changetags',
            'eventName'       => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
            'formType'        => ModifyCompanyTagsType::class,
            'formTheme'       => '@MauticEmail/FormTheme/EmailSendList/emailsend_list_row.html.twig',
        ];
        $event->addEvent(self::TRIGGER_KEY, $newEvent);
    }

    public function onPointExecute(CompanyTagsEvent $event)
    {
        $eventTriggers = $this->companyTriggerModel->getEventRepository()->getPublishedByType(self::TRIGGER_KEY);
        if (empty($eventTriggers)) {
            return;
        }
        $eventLogged    = $this->companyTriggerModel->getEventTriggerLogRepository()->findBy(['company' => $event->getCompany()]);
        $eventLoggedIds = [];
        foreach ($eventLogged as $eventLog) {
            $eventLoggedIds[] = $eventLog->getEvent()->getId();
        }
        foreach ($eventTriggers as $eventTrigger) {
            if (in_array($eventTrigger->getId(), $eventLoggedIds)) {
                continue;
            }

            $trigger = $eventTrigger->getTrigger();
            $company = $event->getCompany();
            if (!isset($company->getField('score_calculated')['value'])) {
                $company->getField('score_calculated')['value'] = 0;
            }

            if ($trigger->getPoints() >= $company->getField('score_calculated')['value']) {
                continue;
            }

            $companiesToAdd    = [];
            $companiesToRemove = [];
            if (!empty($eventTrigger->getProperties()['add_tags'])) {
                $companiesToAdd   = $this->companyTagModel->getRepository()->findBy(['tag'=> $eventTrigger->getProperties()['add_tags']]);
                $tagsAlreadyExist = $this->companyTagModel->getTagsByCompany($company);
                foreach ($companiesToAdd as $key => $companyToAdd) {
                    if (in_array($companyToAdd, $tagsAlreadyExist)) {
                        unset($companiesToAdd[$key]);
                    }
                }
            }
            if (!empty($eventTrigger->getProperties()['remove_tags'])) {
                $companiesToRemove = $this->companyTagModel->getRepository()->findBy(['tag'=> $eventTrigger->getProperties()['remove_tags']]);
            }

            $this->companyTagModel->updateCompanyTags($event->getCompany(), $companiesToAdd, $companiesToRemove);
            $this->companyTriggerModel->saveLog(
                $event->getCompany(),
                $eventTrigger
            );
        }
    }
}

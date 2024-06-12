<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\EmailBundle\Form\Type\EmailSendType;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PointBundle\PointEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyPoint;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerLog;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPointBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanyTriggerType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerExecutedEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
//use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;

class CompanyTagsSubscriber implements EventSubscriberInterface
{

    CONST TRIGGER_KEY = 'companytags.updatetags';

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
//            LeuchtfeuerCompanyPointsEvents::COMPANY_POINT_ON_BUILD   => ['onPointBuild', 0],
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
//            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE => ['onTriggerExecute', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_UPDATE => ['onPointExecute', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_SAVE => ['onPointExecute', 0],
            LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE => ['onPointExecute', 0],
        ];
    }

    public function onPointBuild(CompanyPointBuilderEvent $event): void
    {
        $action = [
            'group' => 'mautic.companytags.actions',
            'label' => 'mautic.companytag.companytags.events.changetags',
//            'callback' => [\Mautic\EmailBundle\Helper\PointEventHelper::class, 'validateEmail'],
            'formType' => ModifyCompanyTagsType::class,

//            'label'       => 'mautic.companytag.companytags.events.changetags',
            'description' => 'mautic.ompanytag.companytags.events.changetags_descr',
//            'formType'    => ModifyCompanyTagsType::class,
            'eventName'   => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD,
        ];
        $event->addAction(self::TRIGGER_KEY, $action);
    }

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group'           => 'mautic.email.point.trigger',
            'label'           => 'mautic.companytag.companytags.events.changetags',
            'eventName' => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
            'formType'        => ModifyCompanyTagsType::class,
//            'formTypeOptions' => ['update_select' => 'pointtriggerevent_properties_email'],
            'formTheme'       => '@MauticEmail/FormTheme/EmailSendList/emailsend_list_row.html.twig',
        ];
        $event->addEvent(self::TRIGGER_KEY, $newEvent);

//        $event->addAction('companytags.change', $action);

//        $action = [
//            'group'    => 'mautic.email.actions',
//            'label'    => 'mautic.email.point.action.send',
//            'callback' => [\Mautic\EmailBundle\Helper\PointEventHelper::class, 'validateEmail'],
//            'formType' => EmailOpenType::class,
//        ];
//
//        $event->addAction('email.send', $action);
    }
//    public function onTriggerExecute(CompanyTriggerExecutedEvent $event)
//    {
//        if (self::TRIGGER_KEY !== $event->getTriggerEvent()->getType()) {
//            return;
//        }
//
//        $properties = $event->getTriggerEvent()->getProperties();
//        $addTags    = $properties['add_tags'] ?: [];
//        $removeTags = $properties['remove_tags'] ?: [];
//        $lead = $event->getLead();
//        $company = $lead->getCompany();
//        dd($company);
////        $this->companyTagModel->updateCompanyTags($event->getCompany(), $addTags, $removeTags);
////        if ($this->leadModel->modifyTags($event->getLead(), $addTags, $removeTags)) {
////            $event->setSucceded();
////        }
//    }

    public function onPointExecute(CompanyTagsEvent $event)
    {

        $eventTriggers = $this->companyTriggerModel->getEventRepository()->getPublishedByType(self::TRIGGER_KEY);
        if (empty($eventTriggers)) {
            return;
        }
        $eventLogged = $this->companyTriggerModel->getEventTriggerLogRepository()->findBy(['company' => $event->getCompany()]);
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

            if ($trigger->getPoints() >= $company->getField('score_calculated')['value']) {
                continue;
            }

            $companiesToAdd = [];
            $companiesToRemove = [];
            if (!empty($eventTrigger->getProperties()['add_tags'])) {
                $companiesToAdd = $this->companyTagModel->getRepository()->findBy(['tag'=> $eventTrigger->getProperties()['add_tags']]);
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



//        $this->companyTriggerLog->



    }

//    public function onPointBuild(PointBuilderEvent $event): void
//    {
//        $action = [
//            'group'    => 'mautic.email.actions',
//            'label'    => 'mautic.email.point.action.open',
//            'callback' => [\Mautic\EmailBundle\Helper\PointEventHelper::class, 'validateEmail'],
//            'formType' => EmailOpenType::class,
//        ];
//
//        $event->addAction('email.open', $action);
//
//        $action = [
//            'group'    => 'mautic.email.actions',
//            'label'    => 'mautic.email.point.action.send',
//            'callback' => [\Mautic\EmailBundle\Helper\PointEventHelper::class, 'validateEmail'],
//            'formType' => EmailOpenType::class,
//        ];
//
//        $event->addAction('email.send', $action);
//    }

}
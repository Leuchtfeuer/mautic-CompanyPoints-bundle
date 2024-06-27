<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use Mautic\FormBundle\Form\Type\SubmitActionEmailType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanySubmitActionEmailType;
//use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\FormSubmitActionUserEmailType;
use Mautic\EmailBundle\Form\Type\FormSubmitActionUserEmailType;

class SendEmailSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.sendemails';

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private MailHelper $mailHelper,
        private UserModel $userModel,
        private EmailModel $emailModel
    )
    {
    }

    public static function getSubscribedEvents()
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
            'group'       => 'mautic.companytags.actions',
            'label'       => 'mautic.companytag.companytags.events.sendemail',
            'formType'    => CompanySubmitActionEmailType::class,
            'formTypeCleanMasks' => [
                'message' => 'raw',
            ],
            'formTheme'          => '@MauticForm/FormTheme/FormAction/_formaction_properties_row.html.twig',
            'eventName'         => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
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

            $properties = $eventTrigger->getProperties();
            if(
                empty($properties['user_id'])
                && empty($properties['to'])
                &&
                    (
                        empty($properties['email_to_owner'])
                    || ( !empty($properties['email_to_owner']) && empty($event->getCompany()->getOwner()) )
                    )
            ){
                continue;
            }

            $users = $this->userModel->getRepository()->findBy(['id'=>$properties['user_id']]);
            foreach ($users as $user){
                $email = $this->emailModel->getRepository()->find($properties['templates']);
                $this->mailHelper->setEmail($email);
                if(!empty($user->getEmail())){
                    $this->mailHelper->addTo($user->getEmail());
                }
                if(!empty($properties['to'])){
                    $this->mailHelper->addTo($properties['to']);
                }
                if(!empty($properties['email_to_owner']) && !empty($event->getCompany()->getOwner())){
                    $owner = $event->getCompany()->getOwner();
                    $this->mailHelper->addTo($owner->getEmail());
                }
                if(!empty($properties['cc'])){
                    $this->mailHelper->addCc($properties['cc']);
                }
                if(!empty($properties['bcc'])){
                    $this->mailHelper->addBcc($properties['bcc']);
                }

                $this->mailHelper->setBody($properties['message']);
                if(!empty($properties['subject'])){
                    $this->mailHelper->setSubject($properties['subject']);
                }
                $this->mailHelper->send();
                $this->mailHelper->reset();
            }
            $this->companyTriggerModel->saveLog(
                $event->getCompany(),
                $eventTrigger
            );

        }



    }
}
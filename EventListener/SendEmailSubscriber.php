<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanySubmitActionEmailType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendEmailSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.sendemails';

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private UserModel $userModel,
        private \Symfony\Contracts\Translation\TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_ON_BUILD                              => ['onEmailBuild', 0],
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
            if (!isset($company->getField('companyscore_calculated')['value']) || null === $company->getField('companyscore_calculated')['value']) {
                $company->getField('score_calculated')['value']        = 0;
                $getFields                                             = $company->getFields();
                $getFields['companyscore_calculated']                  = ['value' => 0];
                $company->setFields($getFields);
            }

            if ($trigger->getPoints() >= $company->getField('companyscore_calculated')['value']) {
                continue;
            }

            $properties = $eventTrigger->getProperties();
            if (
                empty($properties['user_id'])
                && empty($properties['to'])
                && (
                    empty($properties['email_to_owner'])
                    || (!empty($properties['email_to_owner']) && empty($event->getCompany()->getOwner()))
                )
            ) {
                continue;
            }
            $users = $this->userModel->getRepository()->findBy(['id' => $properties['user_id']]);
            $this->companyTriggerModel->sendEmails($users, $properties, $event->getCompany()->getOwner(), $company);
            $this->companyTriggerModel->saveLog(
                $event->getCompany(),
                $eventTrigger
            );
        }
    }

    public function onEmailBuild(EmailBuilderEvent $event): void
    {
        $tokens = [
            '{contactfield=companies.companyscore_calculated}' => $this->translator->trans('mautic.companypoints.companytags.token.label.companyscore_calculated'),
            '{companylist=tags}'                               => $this->translator->trans('mautic.companypoints.companytags.token.label.companytags'),
            '{companylist=segments}'                           => $this->translator->trans('mautic.companypoints.companytags.token.label.companysegments'),
            '{contactfield=companies.points}'                  => $this->translator->trans('mautic.companypoints.companytags.token.label.companyscore'),
        ];

        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens)
            );
            $event->addToken('{contactfield=companyscore_calculated}', '');
        }
    }
}

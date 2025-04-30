<?php


namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanySubmitActionEmailType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendEmailSubscriber implements EventSubscriberInterface
{
    public const TRIGGER_KEY = 'companytags.sendemails';

    public function __construct(
        private CompanyTriggerModel $companyTriggerModel,
        private MailHelper          $mailHelper,
        private UserModel           $userModel,
        private EmailModel          $emailModel,
        private CompanyTagModel     $companyTagModel,
        private CompanySegmentModel $companySegmentModel,
    )
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_BUILD => ['onTriggerBuild', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_UPDATE => ['onPointExecute', 0],
            LeuchtfeuerCompanyTagsEvents::COMPANY_POS_SAVE => ['onPointExecute', 0],
            LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE => ['onPointExecute', 0],
        ];
    }

    public function onTriggerBuild(CompanyTriggerBuilderEvent $event): void
    {
        $newEvent = [
            'group' => 'mautic.companypoints.sendemail.group.actions',
            'label' => 'mautic.companypoints.sendemail.group.actions.sendemail',
            'formType' => CompanySubmitActionEmailType::class,
            'formTypeCleanMasks' => [
                'message' => 'raw',
            ],
            'formTheme' => '@LeuchtfeuerCompanyPoints/FormTheme/FormAction/_formaction_properties_row.html.twig',
            'eventName' => LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_ON_EVENT_EXECUTE,
        ];

        $event->addEvent(self::TRIGGER_KEY, $newEvent);
    }

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
            if (!isset($company->getField('score_calculated')['value'])) {
                $company->getField('score_calculated')['value'] = 0;
            }
            if ($trigger->getPoints() >= $company->getField('score_calculated')['value']) {
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
            foreach ($users as $user) {
                $email = $this->emailModel->getRepository()->find($properties['email']);

                $this->mailHelper->setEmail($email);
                if (!empty($user->getEmail())) {
                    $this->mailHelper->addTo($user->getEmail());
                }
                if (!empty($properties['to'])) {
                    $this->mailHelper->addTo($properties['to']);
                }
                if (!empty($properties['email_to_owner']) && !empty($event->getCompany()->getOwner())) {
                    $owner = $event->getCompany()->getOwner();
                    $this->mailHelper->addTo($owner->getEmail());
                }
                if (!empty($properties['cc'])) {
                    $this->mailHelper->addCc($properties['cc']);
                }
                if (!empty($properties['bcc'])) {
                    $this->mailHelper->addBcc($properties['bcc']);
                }

                $tokens = $this->getTokens($event->getCompany());
                $this->mailHelper->setTokens($tokens);
                $this->mailHelper->send();
                $this->mailHelper->reset();
            }
            $this->companyTriggerModel->saveLog(
                $event->getCompany(),
                $eventTrigger
            );
        }
    }

    private function getTokens(Company $company): array
    {
        $fields = $company->getFields();
        $companyTagsString = $this->getCompanyTagsString($company);
        $companySegmentsString = $this->getCompanySegmentsString($company);

        return [
            '{contactfield=companyname}' => $company->getName(),
            '{contactfield=companycountry}' => $company->getCountry(),
            '{contactfield=companyemail}' => $company->getEmail(),
            '{contactfield=industry_tags}' => $fields['professional']['companyindustry']['value']??'',
            '{contactfield=companyindustry}' => $fields['professional']['companyindustry']['value']??'',
            '{companynumber_of_employees}' => $fields['professional']['companynumber_of_employees']['value']??'',
            '{contactfield=companynumber_of_employees}' => $fields['professional']['companynumber_of_employees']['value']??'',
            '{contactfield=companyannual_revenue}' => $fields['professional']['companyannual_revenue']['value']??'',
            '{companyfield=list_tag_names}' => $companyTagsString,
            '{companyfield=list_segment_names}' => $companySegmentsString,
            '{companyfield=score_calculated}' => $fields['professional']['score_calculated']['value']??'',
        ];
    }

    private function getCompanySegmentsString(Company $company): string
    {
        $companySegments = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(['company' => $company]);
        $companySegmentsString = [];
        foreach ($companySegments as $companySegment) {
            $companySegmentsString[]= $companySegment->getCompanySegment()->getName();
        }
        return implode(', ', $companySegmentsString);
    }

    private function getCompanyTagsString(Company $company): string
    {
        $companyTags = $this->companyTagModel->getTagsByCompany($company);
        $companyTagsString = [];
        foreach ($companyTags as $companyTag) {
            $companyTagsString[]= $companyTag->getName();
        }
        return implode(', ', $companyTagsString);
    }
}

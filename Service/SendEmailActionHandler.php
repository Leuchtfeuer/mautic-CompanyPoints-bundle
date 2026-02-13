<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;

class SendEmailActionHandler
{
    public function __construct(
        private MailHelper $mailHelper,
        private UserModel $userModel,
        private EmailModel $emailModel,
        private CompanyTagModel $companyTagModel,
        private CompanySegmentModel $companySegmentModel,
    ) {
    }

    /**
     * Executes the action of sending an email based on trigger properties.
     *
     * @param Company              $company    the company associated with the event
     * @param array<string, mixed> $properties the properties from the trigger, containing email settings
     */
    public function execute(Company $company, array $properties): void
    {
        $email = $this->emailModel->getEntity($properties['email'] ?? null);
        if (null === $email) {
            return; // No email template found.
        }

        $recipients = [];

        // Gather recipients from 'to' field
        if (!empty($properties['to'])) {
            $recipients[] = $properties['to'];
        }

        // Gather recipient if 'email to owner' is checked
        if (!empty($properties['email_to_owner']) && $company->getOwner()) {
            $recipients[] = $company->getOwner()->getEmail();
        }

        // Gather recipients from the selected user list
        if (!empty($properties['user_id'])) {
            $users = $this->userModel->getRepository()->findBy(['id' => $properties['user_id']]);
            foreach ($users as $user) {
                if ($user->getEmail()) {
                    $recipients[] = $user->getEmail();
                }
            }
        }

        // If there are no recipients at all after checking all sources, do nothing.
        if (empty($recipients)) {
            return;
        }

        $mailer = $this->mailHelper->getMailer();
        $mailer->setEmail($email);

        // Send a single email to all unique recipients
        foreach (array_unique($recipients) as $recipientAddress) {
            $mailer->addTo($recipientAddress);
        }

        // Add CC and BCC if they exist
        if (!empty($properties['cc'])) {
            $mailer->addCc($properties['cc']);
        }
        if (!empty($properties['bcc'])) {
            $mailer->addBcc($properties['bcc']);
        }

        $tokens = $this->getTokens($company);
        $mailer->setTokens($tokens);
        $mailer->send();
    }

    /**
     * @return array<string, string|null>
     */
    private function getTokens(Company $company): array
    {
        $fields                = $company->getFields();
        $companyTagsString     = $this->getCompanyTagsString($company);
        $companySegmentsString = $this->getCompanySegmentsString($company);

        return [
            '{contactfield=companyname}'                => $company->getName(),
            '{contactfield=companycountry}'             => $company->getCountry(),
            '{contactfield=companyemail}'               => $company->getEmail(),
            '{contactfield=industry_tags}'              => $fields['professional']['companyindustry']['value'] ?? '',
            '{contactfield=companyindustry}'            => $fields['professional']['companyindustry']['value'] ?? '',
            '{companynumber_of_employees}'              => $fields['professional']['companynumber_of_employees']['value'] ?? '',
            '{contactfield=companynumber_of_employees}' => $fields['professional']['companynumber_of_employees']['value'] ?? '',
            '{contactfield=companyannual_revenue}'      => $fields['professional']['companyannual_revenue']['value'] ?? '',
            '{companyfield=list_tag_names}'             => $companyTagsString,
            '{companyfield=list_segment_names}'         => $companySegmentsString,
            '{companyfield=score_calculated}'           => $fields['professional']['score_calculated']['value'] ?? '',
        ];
    }

    private function getCompanySegmentsString(Company $company): string
    {
        $companySegments       = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(['company' => $company]);
        $companySegmentsString = [];
        foreach ($companySegments as $companySegment) {
            $companySegmentsString[] = $companySegment->getCompanySegment()->getName();
        }

        return implode(', ', $companySegmentsString);
    }

    private function getCompanyTagsString(Company $company): string
    {
        $companyTags       = $this->companyTagModel->getTagsByCompany($company);
        $companyTagsString = [];
        foreach ($companyTags as $companyTag) {
            $companyTagsString[] = $companyTag->getName();
        }

        return implode(', ', $companyTagsString);
    }
}

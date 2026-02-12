<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadDevice;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Entity\Redirect;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class FunctionalFixtureHelper
{
    private ?User $adminUser = null;

    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client
    ) {
    }


    public function loginAdmin(): void
    {
        if (null === $this->adminUser) {
            $this->adminUser = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
            if (null === $this->adminUser) {
                return;
            }
        }

        $this->client->loginUser($this->adminUser);
    }

    private function logout(): void
    {
        $this->client->request('GET', '/s/logout');
    }

    public function createSegment(string $name, string $alias): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setAlias($alias);
        $segment->setPublicName($name);
        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    public function createEmail(string $name, string $content): Email
    {
        $email = new Email();
        $email->setName($name);
        $email->setSubject($name);
        $email->setCustomHtml($content);
        $email->setEmailType('template');
        $email->setIsPublished(true);
        $this->em->persist($email);
        $this->em->flush();

        return $email;
    }

    public function createContact(string $email): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    public function createCompany(string $name, ?string $email = null): Company
    {
        $company = new Company();
        $company->setName($name);
        if (null !== $email) {
            $company->setEmail($email);
        }
        $this->em->persist($company);

        return $company;
    }

    public function addContactToCompany(Lead $lead, Company $company, \DateTime $dateAdded = null, bool $isPrimary = true): CompanyLead
    {
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setDateAdded($dateAdded ?? new \DateTime());
        $companyLead->setPrimary($isPrimary);
        $this->em->persist($companyLead);

        return $companyLead;
    }

    public function createForm(string $name, string $alias): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setAlias($alias);
        $form->setPostActionProperty('Success');
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    /**
     * Creates a form with email and company fields directly as entities (no API call, no admin login needed).
     */
    public function createFormWithCompany(string $name): Form
    {
        $alias = strtolower(str_replace(' ', '', $name));

        $emailField = new Field();
        $emailField->setLabel('Email');
        $emailField->setAlias('email');
        $emailField->setType('email');
        $emailField->setMappedObject('contact');
        $emailField->setMappedField('email');

        $companyField = new Field();
        $companyField->setLabel('Company');
        $companyField->setAlias('company');
        $companyField->setType('text');
        $companyField->setMappedObject('company');
        $companyField->setMappedField('companyname');

        $submitButton = new Field();
        $submitButton->setLabel('Submit');
        $submitButton->setAlias('submit');
        $submitButton->setType('button');

        $form = new Form();
        $form->setName($name);
        $form->setAlias($alias);
        $form->setFormType('standalone');
        $form->setPostAction('return');
        $form->setPostActionProperty('return');
        $form->setIsPublished(true);

        $form->addField(0, $emailField);
        $form->addField(1, $companyField);
        $form->addField(2, $submitButton);

        $emailField->setForm($form);
        $companyField->setForm($form);
        $submitButton->setForm($form);

        $this->em->persist($emailField);
        $this->em->persist($companyField);
        $this->em->persist($submitButton);
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    public function createFormWithCompanyViaApi(string $name): Form
    {
        $this->loginAdmin();

        $formPayload = [
            'name'        => $name,
            'description' => '',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label'        => 'Company',
                    'type'         => 'text',
                    'alias'        => 'company',
                    'leadField'    => 'companyname',
                    'mappedField'  => 'companyname',
                    'mappedObject' => 'company',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction' => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        if (!isset($response['form']['id'])) {
            throw new \RuntimeException(
                'Form creation via API failed. Response: ' . $clientResponse->getContent()
            );
        }

        $formId = $response['form']['id'];

        // Logout admin after API call
        $this->logout();

        $this->em->clear();
        return $this->em->getRepository(Form::class)->find($formId);
    }

    public function createCompanySegment(string $name, ?string $alias = null): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setPublicName($name);
        if (null !== $alias) {
            $companySegment->setAlias($alias);
        }

        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    public function createCompanyTag(string $tag, ?string $description = null): CompanyTags
    {
        $companyTag = new CompanyTags();
        $companyTag->setTag($tag);
        if (null !== $description) {
            $companyTag->setDescription($description);
        }

        $this->em->persist($companyTag);
        $this->em->flush();

        return $companyTag;
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function createMembershipActivityTrigger(string $name, string $memberActivity, $filter = []): CompanyTrigger
    {
        $trigger = new CompanyTrigger();
        $trigger->setName($name);
        $trigger->setType(CompanyTrigger::TYPE_MEMBER_ACTIVITY);
        $trigger->setCompanySegmentMembershipFilter($filter);
        $trigger->setIsPublished(true);
        $trigger->setMemberActivity($memberActivity);
        $this->em->persist($trigger);
        $this->em->flush();

        return $trigger;
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function createPointTrigger(string $name, $filter = []): CompanyTrigger
    {
        $trigger = new CompanyTrigger();
        $trigger->setName($name);
        $trigger->setType(CompanyTrigger::TYPE_POINTS);
        $trigger->setCompanySegmentMembershipFilter($filter);
        $trigger->setIsPublished(true);
        $trigger->setPoints(1);
        $this->em->persist($trigger);
        $this->em->flush();

        return $trigger;
    }

    public function createCompanyTagsAction(
        CompanyTrigger $trigger,
        string $name,
        array $addTags = [],
        array $removeTags = [],
    ): CompanyTriggerEvent {
        $event = new CompanyTriggerEvent();
        $event->setTrigger($trigger);
        $event->setName($name);
        $event->setType('companytags.updatetags');
        $event->setProperties([
            'add_tags'    => $addTags,
            'remove_tags' => $removeTags,
        ]);
        $event->setOrder(1);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function createModifyContactCampaignsAction(
        CompanyTrigger $trigger,
        string $name,
        array $addToCampaign,
        array $removefromCampaign,
        array $addToOrRestartCampaign,
        string $triggerContacts
    ): CompanyTriggerEvent {
        $event = new CompanyTriggerEvent();
        $event->setTrigger($trigger);
        $event->setName($name);
        $event->setType('companypoints.modifycampaigns');
        $addToCampaignIds = array_map(function ($campaign) {
            return $campaign instanceof \Mautic\CampaignBundle\Entity\Campaign ? $campaign->getId() : $campaign;
        }, $addToCampaign);

        $removeFromCampaignIds = array_map(function ($campaign) {
            return $campaign instanceof \Mautic\CampaignBundle\Entity\Campaign ? $campaign->getId() : $campaign;
        }, $removefromCampaign);
        $addToOrRestartCampaignIds = array_map(function ($campaign) {
            return $campaign instanceof \Mautic\CampaignBundle\Entity\Campaign ? $campaign->getId() : $campaign;
        }, $addToOrRestartCampaign);
        $event->setProperties([
            'triggerContacts'        => $triggerContacts,
            'addToCampaign'          => $addToCampaignIds,
            'removeFromCampaign'     => $removeFromCampaignIds,
            'restartOrAddToCampaign' => $addToOrRestartCampaignIds,
        ]);
        $event->setOrder(1);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    /**
     * @param array<CompanySegment|int> $addSegments
     * @param array<CompanySegment|int> $removeSegments
     */
    public function createCompanySegmentsAction(
        CompanyTrigger $trigger,
        string $name,
        array $addSegments = [],
        array $removeSegments = []
    ): CompanyTriggerEvent {
        $event = new CompanyTriggerEvent();
        $event->setTrigger($trigger);
        $event->setName($name);
        $event->setType('companypoints.modifycompanysegments');
        $addSegmentIds = array_map(function ($segment) {
            return $segment instanceof CompanySegment ? $segment->getId() : $segment;
        }, $addSegments);

        $removeSegmentIds = array_map(function ($segment) {
            return $segment instanceof CompanySegment ? $segment->getId() : $segment;
        }, $removeSegments);

        $event->setProperties([
            'addToLists'      => $addSegmentIds,
            'removeFromLists' => $removeSegmentIds,
        ]);
        $event->setOrder(1);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function createCompanyEmailAction(
        CompanyTrigger $trigger,
        Email $email,
        string $name,
        string $recipient
    ): CompanyTriggerEvent {
        $event = new CompanyTriggerEvent();
        $event->setTrigger($trigger);
        $event->setName($name);
        $event->setType('companytags.sendemails');
        $event->setProperties([
            'email_to_owner' => true,
            'to'             => $recipient,
            'cc'             => '',
            'email'          => $email->getId(),
        ]);
        $event->setOrder(1);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function emulateEmailLinkClicked(Lead $contact): void
    {
        // Create a redirect link
        $redirectUrl = 'https://mautic.org';
        $redirect    = new Redirect();
        $redirect->setRedirectId(uniqid());
        $redirect->setUrl($redirectUrl);
        $this->em->persist($redirect);

        // Create an Email and Stat for tracking context for the contact
        $email    = $this->createEmail('Action Email', 'Click Here');
        $statHash = uniqid('stat', true);
        $stat     = new Stat();
        $stat->setEmail($email);
        $stat->setEmailAddress($contact->getEmail());
        $stat->setDateSent(new \DateTime());
        $stat->setLead($contact);
        $stat->setTrackingHash($statHash);
        $this->em->persist($stat);
        $this->em->flush();
        $this->em->clear();

        $ct = [
            'source'  => ['email', $email->getId()],
            'email'   => $email->getId(),
            'stat'    => $statHash,
            'lead'    => $contact->getId(),
            'channel' => ['email' => $email->getId()],
        ];
        $encodedCt = base64_encode(serialize($ct));

        $this->client->request(Request::METHOD_GET, "/r/{$redirect->getRedirectId()}?ct={$encodedCt}");
    }

    public function emulatePageVisit(Lead $contact): void
    {
        $device = new LeadDevice();
        $device->setDateAdded(new \DateTime());
        $device->setTrackingId(uniqid());
        $device->setLead($contact);
        $this->em->persist($device);
        $this->em->flush();

        $this->client->request('POST', '/mtc/event', [
            'page_url'         => 'https://example.com',
            'mautic_device_id' => $device->getTrackingId(),
        ]);
    }

    public function createLandingPage(string $title = 'LP', string $alias = 'lp', bool $isPublished = true, string $html = '<html><body>LP</body></html>'): Page
    {
        $page = new Page();
        $page->setTitle($title);
        $page->setAlias($alias);
        $page->setIsPublished($isPublished);
        $page->setCustomHtml($html);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    public function emulateFormSubmit(Lead $contact, Company $company = null): void
    {
        // Create tracking device for the contact
        $device = new LeadDevice();
        $device->setDateAdded(new \DateTime());
        $device->setTrackingId(uniqid('device_', true));
        $device->setLead($contact);
        $this->em->persist($device);
        $this->em->flush();

        // Set tracking cookie
        $this->client->getCookieJar()->set(
            new Cookie(
                'mautic_device_id',
                $device->getTrackingId()
            )
        );

        $formData = [
            'mauticform[email]'   => $contact->getEmail(),
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }

        // Use API method (automatically handles admin login/logout)
        $form = $this->createFormWithCompanyViaApi('Test Form');

        $this->submitForm($form, $formData);
    }

    public function emulateFormSubmitWithTracking(Lead $contact, Company $company = null): void
    {
        $formData = [
            'mauticform[email]'   => $contact->getEmail(),
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }
        $form  = $this->createFormWithCompany('Test Form');
        $token = '{form='.$form->getId().'}';
        $this->createLandingPage(alias: 'test-lp', html: "<html><body>{$token}</body></html>");
        $this->client->request('GET', '/test-lp');
        $this->client->enableReboot();
        $this->submitForm($form, $formData);
    }

    /**
     * @param array<string,string> $formData
     */
    public function submitForm(Form $form, array $formData): void
    {
        $formNameForId = strtolower(str_replace(' ', '', $form->getName()));

        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter("form[id=mauticform_{$formNameForId}]");
        $formElement = $formCrawler->form();
        $formElement->setValues($formData);
        $this->client->submit($formElement);
    }

    public function addCompanyToSegment(Company $company, CompanySegment $companySegment): void
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompany($company);
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setDateAdded(new \DateTime());
        $this->em->persist($companiesSegments);
        $this->em->flush();
    }
}

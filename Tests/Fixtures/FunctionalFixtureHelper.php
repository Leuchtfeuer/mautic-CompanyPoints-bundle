<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
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
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class FunctionalFixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client
    ) {
    }

    public function createAndEnablePlugin(): void
    {
        $plugin = new Plugin();
        $plugin->setName('Company Points by Leuchtfeuer');
        $plugin->setBundle('LeuchtfeuerCompanyPointsBundle');
        $this->em->persist($plugin);

        $integration = new Integration();
        $integration->setPlugin($plugin);
        $integration->setIsPublished(true);
        $integration->setName('LeuchtfeuerCompanyPoints');
        $this->em->persist($integration);
        $this->em->flush();
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

    public function createCompany(string $name): Company
    {
        $company = new Company();
        $company->setName($name);
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

    public function createFormWithCompanyViaApi(string $name): Form
    {
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
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
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
            'page_url' => 'https://example.com',
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
        $formData = [
            'mauticform[email]'   => $contact->getEmail()
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }
        $form = $this->createFormWithCompanyViaApi('Test Form');

        $this->submitForm($form, $formData);
    }

    public function emulateFormSubmitWithTracking(Lead $contact, Company $company = null): void
    {
        $formData = [
            'mauticform[email]'   => $contact->getEmail()
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }
        $form = $this->createFormWithCompanyViaApi('Test Form');
        $token = "{form=" . $form->getId() . "}";
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
}

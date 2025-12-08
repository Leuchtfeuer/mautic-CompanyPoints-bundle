<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use PHPUnit\Framework\Assert;

class PointTriggerFunctionalTest extends MauticMysqlTestCase
{
    private FunctionalFixtureHelper $fixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
    }

    public function testCompanyFulfillsCompanySegmentFilterForPointTrigger(): void
    {
        $this->activePlugin();

        /** @var LeadModel $model */
        $model = self::getContainer()->get('mautic.lead.model.lead');

        $segment = $this->fixtureHelper->createCompanySegment('abc');
        $company = $this->fixtureHelper->createCompany('Test1');
        $this->em->flush();
        $this->fixtureHelper->addCompanyToSegment($company, $segment);
        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');

        $trigger = $this->fixtureHelper->createPointTrigger(
            'Tag company on contact click',
            ['operator' => 'in', 'segments' => [$segment->getId()]]
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getTag()]
        );

        $contact =$this->fixtureHelper->createContact('jj@example.com');
        $contact->setCompany($company->getName());
        $contact->setPoints(5);
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->testSymfonyCommand('leuchtfeuer:abm:points-update');

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        /** @var CompanyTagsRepository $tagsRepository */
        $tagsRepository = $this->em->getRepository(CompanyTags::class);
        $tags           = $tagsRepository->getTagsByCompany($company);

        Assert::assertCount(1, $tags, 'Company should have one tag after the link click.');
        Assert::assertSame($companyTag->getId(), $tags[0]->getId(), 'The company was not tagged with the correct tag.');
        Assert::assertSame($companyTag->getTag(), $tags[0]->getTag());
    }

    public function testCompanyDoesNotFulfillCompanySegmentFilterForPointTrigger(): void
    {
        $this->activePlugin();

        /** @var LeadModel $model */
        $model = self::getContainer()->get('mautic.lead.model.lead');

        $segment = $this->fixtureHelper->createCompanySegment('abc');
        $company = $this->fixtureHelper->createCompany('Test1');
        $this->em->flush();
        $companyTag = $this->fixtureHelper->createCompanyTag('Test Tag To Add');

        $trigger = $this->fixtureHelper->createPointTrigger(
            'Tag company on contact click',
            ['operator' => 'in', 'segments' => [$segment->getId()]]
        );

        $this->fixtureHelper->createCompanyTagsAction(
            $trigger,
            'Add Test Tag action',
            [$companyTag->getTag()]
        );

        $contact =$this->fixtureHelper->createContact('jj@example.com');
        $contact->setCompany($company->getName());
        $contact->setPoints(5);
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->testSymfonyCommand('leuchtfeuer:abm:points-update');

        /** @var Company|null $updatedCompany */
        $updatedCompany = $this->em->getRepository(Company::class)->find($company);

        Assert::assertNotNull($updatedCompany);
        /** @var CompanyTagsRepository $tagsRepository */
        $tagsRepository = $this->em->getRepository(CompanyTags::class);
        $tags           = $tagsRepository->getTagsByCompany($company);

        // No company tag added as company did not fulfill segment filter
        Assert::assertCount(0, $tags);
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $nameBundle  = 'LeuchtfeuerCompanyPointsBundle';
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerCompanyPointsIntegration::INTEGRATION_NAME]);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName(str_replace('Bundle', '', $nameBundle));
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);

        $nameBundle2      = 'LeuchtfeuerCompanyTagsBundle';
        $nameIntegration2 = 'LeuchtfeuerCompanyTags';
        $integration2     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration2]);
        if (empty($integration2)) {
            $plugin2      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle2]);
            $integration2 = new Integration();
            $integration2->setName(str_replace('Bundle', '', $nameBundle));
            $integration2->setPlugin($plugin2);
        }
        $integration2->setIsPublished($isPublished);
        $this->em->persist($integration2);

        $this->em->flush();
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support\ActivePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use PHPUnit\Framework\Assert;

class PointTriggerFunctionalTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;
    private FunctionalFixtureHelper $fixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->fixtureHelper->loginAdmin();
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
            [$companyTag->getId()]
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
            [$companyTag->getId()]
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

    public function testModifyCompanySegmentsTriggerActionAdd(): void
    {
        $this->activePlugin();

        $targetSegment = $this->fixtureHelper->createCompanySegment('Target Segment', 'target-segment');
        $company       = $this->fixtureHelper->createCompany('Test Company Inc.');
        $this->em->flush();

        $trigger = $this->fixtureHelper->createPointTrigger('Add company to segment on points');

        $this->fixtureHelper->createCompanySegmentsAction(
            $trigger,
            'Add to target segment',
            [$targetSegment],
            []
        );

        $contact = $this->fixtureHelper->createContact('test@example.com');
        $contact->setCompany($company->getName());
        $contact->setPoints(5);
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $this->em->clear();

        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $companiesSegments = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['company' => $updatedCompany, 'companySegment' => $targetSegment]);

        Assert::assertCount(1, $companiesSegments);
        Assert::assertSame($targetSegment->getId(), $companiesSegments[0]->getCompanySegment()->getId());
    }

    public function testModifyCompanySegmentsTriggerActionRemove(): void
    {
        $this->activePlugin();

        $segmentToRemove = $this->fixtureHelper->createCompanySegment('Segment to Remove', 'segment-to-remove');
        $company         = $this->fixtureHelper->createCompany('Test Company Inc.');
        $this->em->flush();

        $this->fixtureHelper->addCompanyToSegment($company, $segmentToRemove);

        $initialSegments = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['company' => $company, 'companySegment' => $segmentToRemove]);
        Assert::assertCount(1, $initialSegments);

        $trigger = $this->fixtureHelper->createPointTrigger('Remove company from segment on points');

        $this->fixtureHelper->createCompanySegmentsAction(
            $trigger,
            'Remove from segment',
            [],
            [$segmentToRemove]
        );

        $contact = $this->fixtureHelper->createContact('test@example.com');
        $contact->setCompany($company->getName());
        $contact->setPoints(5);
        $this->em->persist($contact);
        $this->em->flush();
        $this->fixtureHelper->addContactToCompany($contact, $company);
        $this->em->flush();

        $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $this->em->clear();

        $updatedCompany = $this->em->getRepository(Company::class)->find($company->getId());
        Assert::assertNotNull($updatedCompany);

        $companiesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['company' => $updatedCompany, 'companySegment' => $segmentToRemove]);

        Assert::assertCount(0, $companiesSegmentsAfter);
    }
}

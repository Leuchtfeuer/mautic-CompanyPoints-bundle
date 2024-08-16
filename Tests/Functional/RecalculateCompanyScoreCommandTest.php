<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;

class RecalculateCompanyScoreCommandTest extends MauticMysqlTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $structureData;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
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
        $this->em->flush();
    }

    public function testRecalculateCompanyScoreCommandNoCompanies(): void
    {
        $this->activePlugin();
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $this->assertStringContainsString('No companies found', $commandTester->getDisplay());
    }

    public function testRecalculateCompanyScoreCommandNotPublished(): void
    {
        $this->activePlugin(false);
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $this->assertStringContainsString('Plugin is not published', $commandTester->getDisplay());
    }

    public function testRecalculateCompanyScoreCommand(): void
    {
        $this->activePlugin();
        $this->createStructure();
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $companies     = $this->em->getRepository(Company::class)->findBy(
            [
                'id' => array_keys($this->structureData['companies']),
            ]
        );
        foreach ($companies as $company) {
            switch ($company->getName()) {
                case 'Company I':
                    $this->checkCompanyIWithScoreAndTwoPoints($company);
                    break;
                case 'Company II':
                    $this->checkCompanyIIWithScoreAndOnePoint($company);
                    break;
                case 'Company III':
                    $this->checkCompanyIIINoScoreNoPoints($company);
                    break;
                case 'Company IV':
                    $this->checkCompanyIVWithScoreAndNoPoints($company);
                    break;
                case 'Company V':
                    $this->checkCompanyVNoScoreTwoPoints($company);
                    break;
                case 'Company VI':
                    $this->checkCompanyVINoScoreOnePointAndOtherNoPoint($company);
                    break;
                case 'Company VII':
                    $this->checkCompanyVIINoScoreNoPoints($company);
                    break;
            }
        }
    }

    private function checkCompanyIWithScoreAndTwoPoints(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(20, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyIIWithScoreAndOnePoint(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(30, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyIIINoScoreNoPoints(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(0, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyIVWithScoreAndNoPoints(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(30, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyVNoScoreTwoPoints(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(15, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyVINoScoreOnePointAndOtherNoPoint(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(10, $companyRefresh->getField('score_calculated')['value']);
    }

    private function checkCompanyVIINoScoreNoPoints(Company $company): void
    {
        $companyRefresh = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertEquals(0, $companyRefresh->getField('score_calculated')['value']);
    }

    private function createStructure(): void
    {
        $companyI = new Company();
        $companyI->setName('Company I');
        $companyI->setScore(10);
        $companyI->setDateAdded(new \DateTime());
        $this->em->persist($companyI);

        $companyII = new Company();
        $companyII->setName('Company II');
        $companyII->setScore(20);
        $companyII->setDateAdded(new \DateTime());
        $this->em->persist($companyII);

        $companyIII = new Company();
        $companyIII->setName('Company III');
        $companyIII->setDateAdded(new \DateTime());
        $this->em->persist($companyIII);

        $companyIV = new Company();
        $companyIV->setName('Company IV');
        $companyIV->setScore(30);
        $companyIV->setDateAdded(new \DateTime());
        $this->em->persist($companyIV);

        $companyV = new Company();
        $companyV->setName('Company V');
        $companyV->setDateAdded(new \DateTime());
        $this->em->persist($companyV);

        $this->em->flush();

        $companyVI = new Company();
        $companyVI->setName('Company VI');
        $companyVI->setDateAdded(new \DateTime());
        $this->em->persist($companyVI);

        $companyVII = new Company();
        $companyVII->setName('Company VII');
        $companyVII->setDateAdded(new \DateTime());
        $this->em->persist($companyVII);

        $leadI = new Lead();
        $leadI->setEmail('example1@example.com');
        $leadI->setFirstName('John');
        $leadI->setCompany($companyI);
        $leadI->setPoints(5);
        $leadI->setDateAdded(new \DateTime());
        $this->em->persist($leadI);

        $leadII = new Lead();
        $leadII->setEmail('example2@example.com');
        $leadII->setFirstName('Jane');
        $leadII->setCompany($companyI);
        $leadII->setPoints(15);
        $leadII->setDateAdded(new \DateTime());
        $this->em->persist($leadII);

        $leadIII = new Lead();
        $leadIII->setEmail('example3@example.com');
        $leadIII->setFirstName('Jack');
        $leadIII->setCompany($companyII);
        $leadIII->setPoints(10);
        $leadIII->setDateAdded(new \DateTime());
        $this->em->persist($leadIII);

        $leadIV = new Lead();
        $leadIV->setEmail('example4@example.com');
        $leadIV->setFirstName('Jill');
        $leadIV->setCompany($companyII);
        $leadIV->setDateAdded(new \DateTime());
        $this->em->persist($leadIV);

        $leadV = new Lead();
        $leadV->setEmail('example5@example.com');
        $leadV->setFirstName('Jim');
        $leadV->setCompany($companyIII);
        $leadV->setPoints(10);
        $leadV->setDateAdded(new \DateTime());
        $this->em->persist($leadV);

        $leadVI = new Lead();
        $leadVI->setEmail('example6@example.com');
        $leadVI->setFirstName('Jenny');
        $leadVI->setCompany($companyIII);
        $leadVI->setPoints(20);
        $leadVI->setDateAdded(new \DateTime());
        $this->em->persist($leadVI);

        $this->em->flush();

        // Company with score I has 2 leads with points
        $companyLeadI = new CompanyLead();
        $companyLeadI->setCompany($companyI);
        $companyLeadI->setLead($leadI);
        $companyLeadI->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadI);

        $companyLeadII = new CompanyLead();
        $companyLeadII->setCompany($companyI);
        $companyLeadII->setLead($leadII);
        $companyLeadII->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadII);

        // Company with score II has 1 lead with points
        $companyLeadIII = new CompanyLead();
        $companyLeadIII->setCompany($companyII);
        $companyLeadIII->setLead($leadIII);
        $companyLeadIII->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadIII);

        // Company with score IV has 1 lead with no points
        $companyLeadIV = new CompanyLead();
        $companyLeadIV->setCompany($companyIV);
        $companyLeadIV->setLead($leadIV);
        $companyLeadIV->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadIV);

        // Company with no score has 2 leads with points
        $companyLeadV = new CompanyLead();
        $companyLeadV->setCompany($companyV);
        $companyLeadV->setLead($leadV);
        $companyLeadV->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadV);

        $companyLeadVI = new CompanyLead();
        $companyLeadVI->setCompany($companyV);
        $companyLeadVI->setLead($leadVI);
        $companyLeadVI->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadVI);

        // Company with no score has 1 lead with no points
        $companyLeadVII = new CompanyLead();
        $companyLeadVII->setCompany($companyVII);
        $companyLeadVII->setLead($leadIV);
        $companyLeadVII->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadVII);

        // Company with no score has 1 lead with no points and 1 lead with points
        $companyLeadVIII = new CompanyLead();
        $companyLeadVIII->setCompany($companyVI);
        $companyLeadVIII->setLead($leadIV);
        $companyLeadVIII->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadVIII);

        $companyLeadIX = new CompanyLead();
        $companyLeadIX->setCompany($companyVI);
        $companyLeadIX->setLead($leadV);
        $companyLeadIX->setDateAdded(new \DateTime());
        $this->em->persist($companyLeadIX);

        // company with no score has no leads
        // $companyIII

        $this->em->flush();

        $this->structureData = [
            'companies' => [
                $companyI->getId()   => $companyI,
                $companyII->getId()  => $companyII,
                $companyIII->getId() => $companyIII,
                $companyIV->getId()  => $companyIV,
                $companyV->getId()   => $companyV,
                $companyVI->getId()  => $companyVI,
                $companyVII->getId() => $companyVII,
            ],
            'leads' => [
                $leadI->getId()   => $leadI,
                $leadII->getId()  => $leadII,
                $leadIII->getId() => $leadIII,
                $leadIV->getId()  => $leadIV,
                $leadV->getId()   => $leadV,
                $leadVI->getId()  => $leadVI,
            ],
        ];
    }

    public function testBatchRecalculateCompanyScoreCommand(): void
    {
        $this->activePlugin();
        $this->createStructure();
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update');
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update', ['--batch-limit' => 3]);
        $this->assertStringContainsString('3 company scores to be recalculated in batches of 3', $commandTester->getDisplay());
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update', ['--batch-limit' => 3]);
        $this->assertStringContainsString('3 company scores to be recalculated in batches of 3', $commandTester->getDisplay());
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:points-update', ['--batch-limit' => 3]);
        $this->assertStringContainsString('1 company scores to be recalculated in batches of 3', $commandTester->getDisplay());
    }
}

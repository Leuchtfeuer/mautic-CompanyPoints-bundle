<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyScoreModel;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RecalculateCompanyScoresCommand extends ModeratedCommand
{
    public function __construct(
        protected PathsHelper $pathsHelper,
        protected CoreParametersHelper $coreParametersHelper,
        protected CompanyScoreModel $companyScoreModel,
        protected CountQueueHelper $countQueueHelper,
        protected Config $config
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this->setName('leuchtfeuer:abm:points-update')
            ->addOption(
                '--batch-limit',
                '-b',
                InputOption::VALUE_OPTIONAL,
                'Set batch size of contacts to process per round. Defaults to 300.',
                300
            )
//            ->addOption(
//                '--list-id',
//                '-i',
//                InputOption::VALUE_OPTIONAL,
//                'Specific ID to rebuild. Defaults to all.',
//                false
//            )
            ->setDescription('Recalculate company scores based on directly assigned points plus algorithm-based aggregation of leads that belong to the points.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published.</error>');

            return 1;
        }
        $output->writeln('');
        $output->writeln('<info>Recalculating company scores...</info>');
        $progressBar           = new ProgressBar($output, 100);
        $batch                 = $input->getOption('batch-limit');
        //        $id                    = $input->getOption('list-id');

        //        if (!$this->checkRunStatus($input, $output, $id)) {
        //            return \Symfony\Component\Console\Command\Command::SUCCESS;
        //        }

        //        $companies = $this->companyModel->getRepository()->findAll();
        //        $helperCount = new CountQueueHelper();
        //        $helperCount->generate();
        //        $query = $this->companyModel->getRepository()->createQueryBuilder('qb');
        //        $query->select('c')->from(Company::class, 'c')->setMaxResults($batch)->setFirstResult(0);
        //        $companies = $query->getQuery()->getResult();
        $offset    = $this->countQueueHelper->getOffset();
        $companies = $this->companyScoreModel->getCompanies($batch, $offset);

        if (empty($companies)) {
            $this->countQueueHelper->resetOffset();
            $output->writeln('<info>No companies found</info>');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }
        $output->writeln('<info>'.count($companies).' company scores to be recalculated in batches of '.$batch.'</info>');
        //        $output->writeln('');
        $progressBar->start(count($companies));

        foreach ($companies as $company) {
            $this->companyScoreModel->recalculateCompanyScores($company);
            $progressBar->advance();
        }
        $this->countQueueHelper->setOffset($offset + $batch);
        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Company scores recalculated</info>');
        $output->writeln('');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}

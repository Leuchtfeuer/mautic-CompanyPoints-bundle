<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyScoreModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RecalculateCompanyScoresCommand extends ModeratedCommand
{
    public function __construct(
        protected PathsHelper $pathsHelper,
        protected CoreParametersHelper $coreParametersHelper,
        protected CompanyScoreModel $companyScoreModel,
        protected CountQueueHelper $countQueueHelper,
        protected Config $config,
        protected EventDispatcherInterface $dispatcher
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
            ->setDescription('Recalculate company scores based on directly assigned points plus algorithm-based aggregation of leads that belong to the points.');
    }

    protected function execute(InputInterface $input, OutputInterface $output, $new = true): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published.</error>');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Recalculating company scores...</info>');
        $progressBar           = new ProgressBar($output, 100);
        $batch                 = $input->getOption('batch-limit');
        $offset                = $this->countQueueHelper->getOffset();
        $companies             = $this->companyScoreModel->getCompanies($batch, $offset);
        // If no companies are found, reset the offset and try again
        if (empty($companies)) {
            $this->countQueueHelper->resetOffset();
            $offset                = $this->countQueueHelper->getOffset();
            $companies             = $this->companyScoreModel->getCompanies($batch, $offset);
        }
        // If still no companies are found in second attempt, exit
        if (empty($companies)) {
            $output->writeln('<info>No companies found</info>');
            $this->countQueueHelper->resetOffset();
            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }
        $output->writeln('<info>'.count($companies).' company scores to be recalculated in batches of '.$batch.'</info>');
        $progressBar->start(count($companies));

        foreach ($companies as $company) {
            $this->companyScoreModel->recalculateCompanyScores($company);
            $this->dispatcher->dispatch(new CompanyTagsEvent($company), LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE);
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

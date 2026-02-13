<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyPostRecalculateEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyScoreModel;
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
        protected EventDispatcherInterface $dispatcher,
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
            ->addOption(
                '--max-companies',
                '-m',
                InputOption::VALUE_OPTIONAL,
                'Set max number of companies to process per Company Point Trigger for this script execution. Defaults to all.',
                false
            )
            ->setDescription('Recalculate company scores based on directly assigned points plus algorithm-based aggregation of leads that belong to the points.');
    }

    protected function execute(InputInterface $input, OutputInterface $output, bool $new = true): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published.</error>');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Recalculating company scores...</info>');
        $maxCompanies          = $input->getOption('max-companies');
        $maxCompanies          = is_numeric($maxCompanies) ? (int) $maxCompanies : null;
        $batch                 = $input->getOption('batch-limit');
        $batch                 = is_numeric($batch) ? (int) $batch : 300;
        $offset                = $this->countQueueHelper->getOffset();

        $remainingCompanies = $this->getRemainingCompanyCount($offset);
        // If no companies are found, reset the offset and try again
        if (0 === $remainingCompanies) {
            $this->countQueueHelper->resetOffset();
            $offset                         = $this->countQueueHelper->getOffset();
            $remainingCompanies             = $this->getRemainingCompanyCount($offset);
        }
        // If still no companies are found in second attempt, exit
        if (0 === $remainingCompanies) {
            $output->writeln('<info>No companies found</info>');
            $this->countQueueHelper->resetOffset();

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        $companiesToProcess = null !== $maxCompanies
            ? min($maxCompanies, $remainingCompanies)
            : $remainingCompanies;

        $batchMessage =
        $output->writeln('<info>'.$companiesToProcess.' company scores to be recalculated in batches of '.$batch.'</info>');

        $progressBar = new ProgressBar($output, $companiesToProcess);
        $progressBar->start();

        $totalProcessed = 0;
        while ($totalProcessed < $companiesToProcess) {
            $currentBatchSize = min($batch, $companiesToProcess - $totalProcessed);
            $companies        = $this->companyScoreModel->getCompanies($currentBatchSize, $offset);
            if (empty($companies)) {
                break;
            }

            foreach ($companies as $company) {
                $this->companyScoreModel->recalculateCompanyScores($company);
                $this->dispatcher->dispatch(new CompanyPostRecalculateEvent($company), LeuchtfeuerCompanyPointsEvents::COMPANY_POST_RECALCULATE);
                $progressBar->advance();
                ++$totalProcessed;
            }

            $offset += count($companies);
            $this->countQueueHelper->setOffset($offset);
        }

        // Reset offset if all companies have been processed
        if (0 === $this->getRemainingCompanyCount($offset)) {
            $this->countQueueHelper->resetOffset();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Company scores recalculated. Total processed: '.$totalProcessed.'</info>');
        $output->writeln('');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    private function getRemainingCompanyCount(int $offset): int
    {
        $qb = $this->companyScoreModel->getRepository()->createQueryBuilder('c');
        $qb->select('COUNT(c.id)');
        $totalCompanies = (int) $qb->getQuery()->getSingleScalarResult();

        return max(0, $totalCompanies - $offset);
    }
}

<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Command;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;
use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class PurgeProcessExecution extends Command
{
    private ManagerRegistry $managerRegistry;
    private string $processLogDir;

    /**
     * @required
     */
    public function setManagerRegistry(ManagerRegistry $managerRegistry): void
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @required
     */
    public function setProcessLogDir(string $processLogDir): void
    {
        $this->processLogDir = $processLogDir;
    }

    protected function configure(): void
    {
        $this->setName('cleverage:process-ui:purge');
        $this->setDescription('Purge process_execution table.');
        $this->setDefinition(
            new InputDefinition([
                new InputOption(
                    'days',
                    'd',
                    InputOption::VALUE_OPTIONAL,
                    'Days to keep. Default 180',
                    180
                ),
                new InputOption(
                    'remove-files',
                    'rf',
                    InputOption::VALUE_NEGATABLE,
                    'Remove log files ? (default false)',
                    false
                ),
            ])
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days');
        $removeFiles = $input->getOption('remove-files');
        $date = new \DateTime();
        $date->modify("-$days day");
        if ($removeFiles) {
            $finder = new Finder();
            $fs = new Filesystem();
            $finder->in($this->processLogDir)->date('before '.$date->format(DateTimeInterface::ATOM));
            $count = $finder->count();
            $fs->remove($finder);
            $output->writeln("<info>$count log files are deleted on filesystem.</info>");
        }
        /** @var ProcessExecutionRepository $repository */
        $repository = $this->managerRegistry->getRepository(ProcessExecution::class);
        $repository->deleteBefore($date);

        $output->writeln(<<<EOT
            <info>Process Execution before {$date->format(DateTimeInterface::ATOM)} are deleted into database.</info>
            EOT);

        return Command::SUCCESS;
    }
}

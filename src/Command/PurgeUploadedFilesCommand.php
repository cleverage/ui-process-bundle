<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Deletes the files of the upload directory written by the bundle (launch form, HTTP API) and older than
 * "cleverage_ui_process.uploaded_files_max_days" days: those left when the execution did not delete them (dispatch
 * failure, worker killed, message removed from the failure transport...).
 *
 * Only the files directly in the upload directory, named as the bundle names them, are deleted: never a file put there
 * by the application (e.g. given by its path to a schedule), nor the content of its subdirectories.
 *
 * @internal
 */
#[AsCommand(
    name: 'cleverage:ui-process:purge-uploaded-files',
    description: 'Delete the files sent as input of a process (launch form, HTTP API) left in the upload directory, older than the configured retention.'
)]
class PurgeUploadedFilesCommand extends Command
{
    /**
     * Names of the files written by the bundle: <uuid v4>[.<extension>] by the launch form (LaunchAction),
     * <YmdHis>_<uniqid>_<client file name> by the HTTP API (HttpProcessExecuteValueResolver).
     */
    private const UPLOADED_FILE_PATTERNS = [
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}(\.[a-z0-9]{1,16})?$/i',
        '/^\d{14}_[0-9a-f]{13}_/',
    ];

    public function __construct(
        private readonly string $uploadDirectory,
        private readonly ?int $maxDays = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the files to delete, without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if (null === $this->maxDays) {
            $style->success('Nothing to purge: no retention configured ("cleverage_ui_process.uploaded_files_max_days" parameter).');

            return Command::SUCCESS;
        }
        if ($this->maxDays < 1) {
            $style->error(\sprintf('The "cleverage_ui_process.uploaded_files_max_days" parameter must be at least 1 (or null to keep the files), %d given.', $this->maxDays));

            return Command::FAILURE;
        }
        if (!is_dir($this->uploadDirectory)) {
            $style->success(\sprintf('Nothing to purge: the upload directory "%s" does not exist.', $this->uploadDirectory));

            return Command::SUCCESS;
        }

        $limit = (new \DateTimeImmutable(\sprintf('-%d days', $this->maxDays)))->getTimestamp();
        $finder = (new Finder())->files()->in($this->uploadDirectory)->depth(0)
            ->filter(static function (\SplFileInfo $file) use ($limit): bool {
                if ($file->isLink() || $file->getMTime() >= $limit) {
                    return false;
                }

                return array_any(self::UPLOADED_FILE_PATTERNS, static fn (string $pattern): bool => 1 === preg_match($pattern, $file->getFilename()));
            });
        $files = array_keys(iterator_to_array($finder));
        if (!$dryRun) {
            (new Filesystem())->remove($files);
        }

        $style->success(\sprintf(
            $dryRun ? '%d uploaded file(s) older than %d day(s) to delete (dry run).' : '%d uploaded file(s) older than %d day(s) deleted.',
            \count($files),
            $this->maxDays
        ));

        return Command::SUCCESS;
    }
}

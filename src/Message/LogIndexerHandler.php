<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessUiBundle\Entity\ProcessExecutionLogRecord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Dubture\Monolog\Parser\LineLogParser;
use Monolog\Logger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class LogIndexerHandler implements MessageHandlerInterface
{
    public const INDEX_LOG_RECORD = 'index_log_record';
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(LogIndexerMessage $logIndexerMessage): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = $this->managerRegistry->getManagerForClass(ProcessExecutionLogRecord::class);
        $table = $manager->getClassMetadata(ProcessExecutionLogRecord::class)->getTableName();
        $file = new \SplFileObject($logIndexerMessage->getLogPath());
        $file->seek($logIndexerMessage->getStart());
        $offset = $logIndexerMessage->getOffset();
        $parser = new LineLogParser();
        $parameters = [];
        while ($offset > 0 && !$file->eof()) {
            /** @var string $currentLine */
            $currentLine = $file->current();
            $parsedLine = $parser->parse($currentLine);
            if (!empty($parsedLine) && true === ($parsedLine['context'][self::INDEX_LOG_RECORD] ?? false)) {
                $parameters[] = $logIndexerMessage->getProcessExecutionId();
                $parameters[] = Logger::toMonologLevel($parsedLine['level']);
                $parameters[] = substr($parsedLine['message'], 0, 255);
            }
            $file->next();
            --$offset;
        }
        if (\count($parameters) > 0) {
            $statement = $this->getStatement($table, (int) (\count($parameters) / 3));
            $manager->getConnection()->executeStatement($statement, $parameters);
        }
    }

    private function getStatement(string $table, int $size): string
    {
        $sql = 'INSERT INTO '.$table.' (process_execution_id, log_level, message) VALUES ';
        while ($size > 0) {
            $sql .= $size > 1 ? '(?, ?, ?),' : '(?, ?, ?)';
            --$size;
        }

        return $sql;
    }
}

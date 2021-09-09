<?php
namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessUiBundle\Entity\ProcessExecutionLogRecord;
use Doctrine\Persistence\ManagerRegistry;
use Dubture\Monolog\Parser\LineLogParser;
use Monolog\Logger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class LogIndexerHandler implements MessageHandlerInterface
{
    private ManagerRegistry $managerRegistry;

    private string $indexLogsLevel;

    public function __construct(ManagerRegistry $managerRegistry, string $indexLogsLevel)
    {
        $this->managerRegistry = $managerRegistry;
        $this->indexLogsLevel = $indexLogsLevel;
    }

    public function __invoke(LogIndexerMessage $logIndexerMessage)
    {
        $indexLogsLevel = Logger::toMonologLevel($this->indexLogsLevel);
        $manager = $this->managerRegistry->getManagerForClass(ProcessExecutionLogRecord::class);
        if (null === $manager) {
            return;
        }
        $table = $manager->getClassMetadata(ProcessExecutionLogRecord::class)->getTableName();
        $file = new \SplFileObject($logIndexerMessage->getLogPath());
        $file->seek($logIndexerMessage->getStart());
        $offset = $logIndexerMessage->getOffset();
        $parser = new LineLogParser();
        $parameters = [];
        while ($offset > 0 && !$file->eof()) {
            $parsedLine = $parser->parse($file->current());
            if (!empty($parsedLine) && Logger::toMonologLevel($parsedLine['level']) >= $indexLogsLevel) {
                $parameters[] = $logIndexerMessage->getProcessExecutionId();
                $parameters[] = Logger::toMonologLevel($parsedLine['level']);
                $parameters[] = $parsedLine['message'];
            }
            $file->next();
            --$offset;
        }
        $statement = $this->getStatement($table, count($parameters) / 3);
        $manager->getConnection()->executeStatement($statement, $parameters);
    }

    private function getStatement(string $table, int $size): string
    {
        $sql = 'INSERT INTO '. $table .' (process_execution_id, log_level, message) VALUES ';
        while ($size > 0) {
            $sql .= $size >1 ? '(?, ?, ?),' : '(?, ?, ?)' ;
            --$size;
        }

        return $sql;
    }
}

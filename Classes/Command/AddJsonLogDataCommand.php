<?php

/*
 * This file is part of the EXT:faster_syslog_serialization project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to execute the original v12 upgrade wizard on a custom field to pre-update
 */
class AddJsonLogDataCommand extends Command
{
    private const TABLE_NAME = 'sys_log';

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Bla bla');
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable(self::TABLE_NAME);

        // Perform fast update of a:0:{}, since it evaluates to []
        $updated = $connection->update(
            self::TABLE_NAME,
            ['log_data_json' => '[]'],
            ['log_data' => 'a:0:{}', 'log_data_json' => '']
        );
        if ($output->isVerbose()) {
            $output->writeln(sprintf('Updated %s rows that represented an empty array', (string)$updated));
        }

        // Perform fast update of a:1:{i:0;s:0:"";}, since it evaluates to [""]
        $updated = $connection->update(
            self::TABLE_NAME,
            ['log_data_json' => '[""]'],
            ['log_data' => 'a:1:{i:0;s:0:"";}', 'log_data_json' => '']
        );
        if ($output->isVerbose()) {
            $output->writeln(sprintf('Updated %s rows that represented an array containing just a single empty string', (string)$updated));
        }

        $queryBuilder = $this->getPreparedQueryBuilder();
        $result = $queryBuilder
            ->select('uid', 'log_data')
            ->where(
                $queryBuilder->expr()->like('log_data', $queryBuilder->createNamedParameter('a:%')),
                $queryBuilder->expr()->eq('log_data_json', $queryBuilder->createNamedParameter(''))
            )
            ->executeQuery();

        while ($record = $result->fetchAssociative()) {
            $logData = $this->unserializeLogData($record['log_data'] ?? '');
            $connection->update(
                self::TABLE_NAME,
                ['log_data_json' => json_encode($logData)],
                ['uid' => (int)$record['uid']]
            );
            if ($output->isVerbose()) {
                $output->writeln(sprintf('Updated row %s', (string)$record['uid']));
            }
        }

        return 0;
    }

    protected function getPreparedQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->from(self::TABLE_NAME);
        return $queryBuilder;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * Useful for handling old serialized data, which might have been migrated to JSON encoded
     * properties already.
     */
    protected function unserializeLogData(mixed $logData): ?array
    {
        // The @ symbol avoids an E_NOTICE when unserialize() fails
        $cleanedUpData = @unserialize((string)$logData, ['allowed_classes' => false]);
        if ($cleanedUpData === false) {
            $cleanedUpData = json_decode((string)$logData, true);
        }
        return is_array($cleanedUpData) ? $cleanedUpData : null;
    }
}

<?php

declare(strict_types=1);

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

namespace Pschriner\FasterSyslogSerializationMigration\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\LogDataTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Replacement update to copy the prefilled log_data_json column to log_data
 */
#[UpgradeWizard('sysLogSerialization')]
class SysLogSerializationUpdate implements UpgradeWizardInterface
{
    use LogDataTrait;
    private const TABLE_NAME = 'sys_log';

    public function getTitle(): string
    {
        return 'Migrate sys_log entries to a JSON formatted value.';
    }

    public function getDescription(): string
    {
        return 'All sys_log_entries are now updated to contain JSON values in the "log_data" field.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->hasRecordsToUpdate();
    }

    public function executeUpdate(): bool
    {
        // we don't want any escaping, that's why we choose the concrete query builder
        $concreteQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_NAME)->getConcreteQueryBuilder();

        $concreteQueryBuilder->update(self::TABLE_NAME)
            ->set('log_data', 'log_data_json')
            ->where($concreteQueryBuilder->expr()->neq('log_data_json', ''))
            ->executeStatement();

        if ($this->hasRecordsToUpdate()) {
            return false;
        }

        return true;
    }

    /**
     * Slightly different implementation because fetching one is far cheaper than counting all
     * (as mysql will break at the first result)
     */
    protected function hasRecordsToUpdate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder();
        return (bool)$queryBuilder
            ->select('uid')
            ->where(
                $queryBuilder->expr()->like('log_data', $queryBuilder->createNamedParameter('a:%'))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
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
}

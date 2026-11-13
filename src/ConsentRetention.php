<?php

namespace Mouseketeers\ConsentRecords;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;

/**
 * Deletes consent records. Shared by {@link ConsentRetentionTask} (manual runs)
 * and {@link ConsentRetentionJob} (scheduled runs).
 */
class ConsentRetention
{
    use Configurable;

    /**
     * Records older than this many days are deleted by the scheduled job and by the
     * task when it is run without options. 0 turns scheduled retention off.
     *
     * @config
     */
    private static $retention_days = 0;

    /**
     * Days between runs of the scheduled job.
     *
     * @config
     */
    private static $job_interval_days = 7;

    public static function retentionDays(): int
    {
        return max(0, (int) static::config()->get('retention_days'));
    }

    /**
     * Delete records older than $olderThanDays and/or with the given ConsentID. When both
     * are given a record must match both. Needs at least one, so it can never delete everything.
     *
     * @return int Number of records deleted
     */
    public static function purge(int $olderThanDays = 0, string $consentID = ''): int
    {
        if ($olderThanDays <= 0 && $consentID === '') {
            throw new InvalidArgumentException('Give olderThanDays or a consentID.');
        }

        $where = [];
        if ($olderThanDays > 0) {
            $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - $olderThanDays * 86400);
            $where['"Created" < ?'] = [$cutoff];
        }
        if ($consentID !== '') {
            $where['"ConsentID" = ?'] = [$consentID];
        }

        // One statement instead of loading and deleting every record: the table can be large.
        SQLDelete::create('"' . DataObject::getSchema()->tableName(ConsentRecord::class) . '"', $where)->execute();

        return DB::affected_rows();
    }
}

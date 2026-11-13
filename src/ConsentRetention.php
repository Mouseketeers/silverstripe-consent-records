<?php

namespace Mouseketeers\ConsentRecords;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;

/**
 * Deletes consent records. Shared by {@link ConsentRetentionTask} (manual runs)
 * and {@link ConsentRetentionJob} (scheduled runs).
 *
 * Other modules can clean up data that belongs to the records through an extension
 * hook, called with each batch just before it is deleted:
 *
 *   public function onBeforePurge(array $records): void   // ConsentRecord[]
 *
 * consent-userforms uses it to delete the linked submissions.
 */
class ConsentRetention
{
    use Configurable;
    use Extensible;
    use Injectable;

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

    /**
     * Records handled (hook, then delete) at a time.
     *
     * @config
     */
    private static $batch_size = 500;

    public static function retentionDays(): int
    {
        return max(0, (int) static::config()->get('retention_days'));
    }

    /**
     * Delete records older than $olderThanDays and/or with the given ConsentID. When both
     * are given a record must match both. Needs at least one, so it can never delete everything.
     *
     * Works in batches: the hook runs before each batch is deleted, so a failure leaves the
     * records in place and a second run finishes the job.
     *
     * @return int Number of records deleted
     */
    public static function purge(int $olderThanDays = 0, string $consentID = ''): int
    {
        if ($olderThanDays <= 0 && $consentID === '') {
            throw new InvalidArgumentException('Give olderThanDays or a consentID.');
        }

        $records = ConsentRecord::get();
        if ($olderThanDays > 0) {
            $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - $olderThanDays * 86400);
            $records = $records->filter('Created:LessThan', $cutoff);
        }
        if ($consentID !== '') {
            $records = $records->filter('ConsentID', $consentID);
        }

        $self = static::singleton();
        $table = DataObject::getSchema()->tableName(ConsentRecord::class);
        $batchSize = max(1, (int) static::config()->get('batch_size'));
        $deleted = 0;

        // Each pass deletes what it selected, so the same query returns the next batch.
        while (($batch = $records->sort('ID')->limit($batchSize)->toArray())) {
            $self->extend('onBeforePurge', $batch);

            $ids = array_map(fn ($record) => (int) $record->ID, $batch);
            SQLDelete::create('"' . $table . '"', ['"ID" IN (' . implode(',', $ids) . ')'])->execute();
            $deleted += count($ids);
        }

        return $deleted;
    }
}

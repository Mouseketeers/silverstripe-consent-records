<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Deletes consent records: those older than a retention period, or every record for
 * one ConsentID (e.g. a GDPR erasure request).
 *
 * Run: sake dev/tasks/consent-retention older-than-days=730
 *      sake dev/tasks/consent-retention consent-id=jo@example.com
 *      sake dev/tasks/consent-retention                 (uses ConsentRetention.retention_days)
 *      sake dev/tasks/consent-retention schedule=1      (starts the recurring queued job)
 */
class ConsentRetentionTask extends BuildTask
{
    protected $title = 'Consent record retention / erasure';

    protected $description = 'Deletes consent records older than older-than-days, or all records for consent-id. Without options it uses ConsentRetention.retention_days. schedule=1 starts the recurring queued job.';

    private static $segment = 'consent-retention';

    public function run($request)
    {
        if ($request->getVar('schedule')) {
            $this->schedule();
            return;
        }

        $days = (int) $request->getVar('older-than-days');
        $consentID = (string) $request->getVar('consent-id');

        if ($days <= 0 && $consentID === '') {
            $days = ConsentRetention::retentionDays();
        }

        if ($days <= 0 && $consentID === '') {
            $this->write('Give older-than-days (> 0) or consent-id, or set ConsentRetention.retention_days.');
            return;
        }

        $this->write('Deleted ' . ConsentRetention::purge($days, $consentID) . ' consent record(s).');
    }

    private function schedule(): void
    {
        if (!class_exists(QueuedJobService::class)) {
            $this->write('Install symbiote/silverstripe-queuedjobs to schedule the job.');
            return;
        }

        if (ConsentRetention::retentionDays() === 0) {
            $this->write('Set ConsentRetention.retention_days first: with 0 the job would do nothing.');
            return;
        }

        // queueJob() ignores a job whose signature is already waiting, so running this twice is safe.
        QueuedJobService::singleton()->queueJob(new ConsentRetentionJob());
        $this->write('Consent retention job queued.');
    }

    private function write(string $message): void
    {
        echo Director::is_cli() ? $message . PHP_EOL : '<p>' . htmlspecialchars($message) . '</p>';
    }
}

<?php

namespace Mouseketeers\ConsentRecords;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Deletes consent records older than ConsentRetention.retention_days and queues its
 * next run. Needs symbiote/silverstripe-queuedjobs. Start it once with
 * `sake tasks:consent-retention --schedule`; it then repeats itself.
 *
 * Does nothing, and does not repeat, while retention_days is 0.
 */
class ConsentRetentionJob extends AbstractQueuedJob implements QueuedJob
{
    public function getTitle()
    {
        return 'Delete consent records older than ' . ConsentRetention::retentionDays() . ' days';
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        $days = ConsentRetention::retentionDays();

        $this->totalSteps = 1;
        $this->currentStep = 1;
        $this->isComplete = true;

        if ($days === 0) {
            $this->addMessage('ConsentRetention.retention_days is 0: nothing deleted, not rescheduled.');
            return;
        }

        $this->addMessage('Deleted ' . ConsentRetention::purge($days) . ' consent record(s).');

        $interval = max(1, (int) ConsentRetention::config()->get('job_interval_days')) * 86400;
        QueuedJobService::singleton()->queueJob(new self(), date('Y-m-d H:i:s', time() + $interval));
    }
}

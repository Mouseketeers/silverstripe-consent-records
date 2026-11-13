<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Deletes consent records: those older than a retention period, or every record for
 * one ConsentID (e.g. a GDPR erasure request).
 *
 * Run: sake tasks:consent-retention --older-than-days=730
 *      sake tasks:consent-retention --consent-id=jo@example.com
 *      sake tasks:consent-retention                 (uses ConsentRetention.retention_days)
 *      sake tasks:consent-retention --schedule      (starts the recurring queued job)
 */
class ConsentRetentionTask extends BuildTask
{
    protected string $title = 'Consent record retention / erasure';

    protected static string $description = 'Deletes consent records older than --older-than-days, or all records for --consent-id. Without options it uses ConsentRetention.retention_days. --schedule starts the recurring queued job.';

    protected static string $commandName = 'consent-retention';

    public function getOptions(): array
    {
        return [
            new InputOption('older-than-days', null, InputOption::VALUE_REQUIRED, 'Delete records created more than this many days ago'),
            new InputOption('consent-id', null, InputOption::VALUE_REQUIRED, 'Delete all records for this ConsentID'),
            new InputOption('schedule', null, InputOption::VALUE_NONE, 'Queue the recurring retention job (needs symbiote/silverstripe-queuedjobs)'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if ($input->getOption('schedule')) {
            return $this->schedule($output);
        }

        $days = (int) $input->getOption('older-than-days');
        $consentID = (string) $input->getOption('consent-id');

        if ($days <= 0 && $consentID === '') {
            $days = ConsentRetention::retentionDays();
        }

        if ($days <= 0 && $consentID === '') {
            $output->writeln('Give --older-than-days (> 0) or --consent-id, or set ConsentRetention.retention_days.');
            return Command::INVALID;
        }

        $output->writeln('Deleted ' . ConsentRetention::purge($days, $consentID) . ' consent record(s).');

        return Command::SUCCESS;
    }

    private function schedule(PolyOutput $output): int
    {
        if (!class_exists(QueuedJobService::class)) {
            $output->writeln('Install symbiote/silverstripe-queuedjobs to schedule the job.');
            return Command::FAILURE;
        }

        if (ConsentRetention::retentionDays() === 0) {
            $output->writeln('Set ConsentRetention.retention_days first: with 0 the job would do nothing.');
            return Command::INVALID;
        }

        // queueJob() ignores a job whose signature is already waiting, so running this twice is safe.
        QueuedJobService::singleton()->queueJob(new ConsentRetentionJob());
        $output->writeln('Consent retention job queued.');

        return Command::SUCCESS;
    }
}

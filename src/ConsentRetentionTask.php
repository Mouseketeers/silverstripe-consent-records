<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Deletes consent records, either those older than a retention period or
 * every record for one ConsentID (e.g. a GDPR erasure request).
 *
 * Run: sake tasks:consent-retention --older-than-days=1095
 *      sake tasks:consent-retention --consent-id=jo@example.com
 */
class ConsentRetentionTask extends BuildTask
{
    protected string $title = 'Consent record retention / erasure';

    protected static string $description = 'Deletes consent records older than --older-than-days, or all records for --consent-id.';

    protected static string $commandName = 'consent-retention';

    public function getOptions(): array
    {
        return [
            new InputOption('older-than-days', null, InputOption::VALUE_REQUIRED, 'Delete records created more than this many days ago'),
            new InputOption('consent-id', null, InputOption::VALUE_REQUIRED, 'Delete all records for this ConsentID'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) $input->getOption('older-than-days');
        $consentID = (string) $input->getOption('consent-id');

        if ($days <= 0 && $consentID === '') {
            $output->writeln('Give --older-than-days (> 0) or --consent-id.');
            return Command::INVALID;
        }

        $records = ConsentRecord::get();
        if ($consentID !== '') {
            $records = $records->filter('ConsentID', $consentID);
        }
        if ($days > 0) {
            $records = $records->filter('Created:LessThan', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        }

        $count = 0;
        foreach ($records as $record) {
            $record->delete();
            $count++;
        }
        $output->writeln("Deleted {$count} consent record(s).");

        return Command::SUCCESS;
    }
}

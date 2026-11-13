<?php

namespace Mouseketeers\ConsentRecords\Tests;

use InvalidArgumentException;
use Mouseketeers\ConsentRecords\ConsentRecord;
use Mouseketeers\ConsentRecords\ConsentRetention;
use Mouseketeers\ConsentRecords\ConsentRetentionJob;
use Mouseketeers\ConsentRecords\ConsentRetentionTask;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;

class ConsentRetentionTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** Three records: 400, 100 and 0 days old. */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['old@example.com', '-400 days'], ['mid@example.com', '-100 days'], ['new@example.com', 'now']] as [$id, $when]) {
            $record = ConsentRecord::create(['ConsentID' => $id, 'ConsentType' => 'X', 'URL' => 'https://example.com/']);
            $record->write();
            DB::prepared_query('UPDATE "ConsentRecord" SET "Created" = ? WHERE "ID" = ?', [date('Y-m-d H:i:s', strtotime($when)), $record->ID]);
        }
    }

    private function remaining(): array
    {
        return ConsentRecord::get()->sort('ConsentID')->column('ConsentID');
    }

    public function testPurgeByAge()
    {
        $this->assertSame(1, ConsentRetention::purge(365));
        $this->assertSame(['mid@example.com', 'new@example.com'], $this->remaining());
    }

    public function testPurgeByConsentID()
    {
        $this->assertSame(1, ConsentRetention::purge(0, 'mid@example.com'));
        $this->assertSame(['new@example.com', 'old@example.com'], $this->remaining());
    }

    public function testBothCriteriaMustMatch()
    {
        $this->assertSame(0, ConsentRetention::purge(30, 'new@example.com'));
        $this->assertCount(3, $this->remaining());
    }

    public function testPurgeWithoutCriteriaIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        ConsentRetention::purge();
    }

    private function runTask(array $args): int
    {
        $task = new ConsentRetentionTask();

        return $task->run(new ArrayInput($args, new InputDefinition($task->getOptions())), new PolyOutput(PolyOutput::FORMAT_ANSI));
    }

    public function testTaskWithoutOptionsUsesTheConfiguredPeriod()
    {
        $this->assertSame(Command::INVALID, $this->runTask([]));
        $this->assertCount(3, $this->remaining());

        Config::modify()->set(ConsentRetention::class, 'retention_days', 365);
        $this->assertSame(Command::SUCCESS, $this->runTask([]));
        $this->assertSame(['mid@example.com', 'new@example.com'], $this->remaining());
    }

    public function testJobDeletesAndQueuesItsNextRun()
    {
        if (!class_exists(QueuedJobDescriptor::class)) {
            $this->markTestSkipped('symbiote/silverstripe-queuedjobs is not installed.');
        }

        Config::modify()->set(ConsentRetention::class, 'retention_days', 365);
        $job = new ConsentRetentionJob();
        $job->process();

        $this->assertSame(['mid@example.com', 'new@example.com'], $this->remaining());
        $this->assertTrue($job->jobFinished());
        $this->assertSame(1, QueuedJobDescriptor::get()->filter('Implementation', ConsentRetentionJob::class)->count());
    }

    public function testDisabledJobDeletesNothingAndDoesNotRepeat()
    {
        if (!class_exists(QueuedJobDescriptor::class)) {
            $this->markTestSkipped('symbiote/silverstripe-queuedjobs is not installed.');
        }

        $job = new ConsentRetentionJob();
        $job->process();

        $this->assertCount(3, $this->remaining());
        $this->assertSame(0, QueuedJobDescriptor::get()->count());
    }

    public function testScheduleIsSafeToRepeat()
    {
        if (!class_exists(QueuedJobDescriptor::class)) {
            $this->markTestSkipped('symbiote/silverstripe-queuedjobs is not installed.');
        }

        $this->assertSame(Command::INVALID, $this->runTask(['--schedule' => true]), 'refused while retention is off');

        Config::modify()->set(ConsentRetention::class, 'retention_days', 365);
        $this->assertSame(Command::SUCCESS, $this->runTask(['--schedule' => true]));
        $this->runTask(['--schedule' => true]);
        $this->assertSame(1, QueuedJobDescriptor::get()->filter('Implementation', ConsentRetentionJob::class)->count());
    }
}

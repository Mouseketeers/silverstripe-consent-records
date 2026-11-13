<?php

namespace Mouseketeers\ConsentRecords\Tests;

use InvalidArgumentException;
use Mouseketeers\ConsentRecords\ConsentRecord;
use Mouseketeers\ConsentRecords\ConsentRetention;
use Mouseketeers\ConsentRecords\ConsentRetentionJob;
use Mouseketeers\ConsentRecords\ConsentRetentionTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/** Records what the onBeforePurge hook is called with. */
class RecordingPurgeExtension extends Extension
{
    public static array $batches = [];

    public function onBeforePurge(array $records): void
    {
        self::$batches[] = array_map(fn ($record) => $record->ConsentID, $records);
    }
}

class ConsentRetentionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $required_extensions = [
        ConsentRetention::class => [RecordingPurgeExtension::class],
    ];

    /** Three records: 400, 100 and 0 days old. */
    protected function setUp(): void
    {
        parent::setUp();
        RecordingPurgeExtension::$batches = [];

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

    public function testHookSeesEachBatchAndEveryRecordIsDeleted()
    {
        Config::modify()->set(ConsentRetention::class, 'batch_size', 2);
        for ($i = 0; $i < 5; $i++) {
            ConsentRecord::create(['ConsentID' => 'bulk@example.com', 'ConsentType' => 'X', 'URL' => 'https://example.com/'])->write();
        }

        $this->assertSame(5, ConsentRetention::purge(0, 'bulk@example.com'));

        $this->assertSame([2, 2, 1], array_map('count', RecordingPurgeExtension::$batches));
        $this->assertCount(3, $this->remaining(), 'other records are untouched');
    }

    public function testPurgeWithoutCriteriaIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        ConsentRetention::purge();
    }

    private function runTask(array $vars): string
    {
        ob_start();
        (new ConsentRetentionTask())->run(new HTTPRequest('GET', '/', $vars));

        return trim(ob_get_clean());
    }

    public function testTaskWithoutOptionsUsesTheConfiguredPeriod()
    {
        $this->assertStringContainsString('Give older-than-days', $this->runTask([]));
        $this->assertCount(3, $this->remaining());

        Config::modify()->set(ConsentRetention::class, 'retention_days', 365);
        $this->assertStringContainsString('Deleted 1', $this->runTask([]));
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

        $this->assertStringContainsString('Set ConsentRetention.retention_days first', $this->runTask(['schedule' => 1]), 'refused while retention is off');

        Config::modify()->set(ConsentRetention::class, 'retention_days', 365);
        $this->assertStringContainsString('job queued', $this->runTask(['schedule' => 1]));
        $this->runTask(['schedule' => 1]);
        $this->assertSame(1, QueuedJobDescriptor::get()->filter('Implementation', ConsentRetentionJob::class)->count());
    }
}

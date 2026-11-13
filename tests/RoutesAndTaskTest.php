<?php

namespace Mouseketeers\ConsentRecords\Tests;

use Mouseketeers\ConsentRecords\ConsentRecord;
use Mouseketeers\ConsentRecords\ConsentRetentionTask;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\FunctionalTest;

/**
 * Goes through the real router and task runner: the class names in
 * _config/routes.yml and the task's properties are only checked there.
 */
class RoutesAndTaskTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private function banner(array $override = []): string
    {
        return json_encode($override + ['ConsentType' => 'CookieConsent', 'ConsentID' => 'uuid-1', 'URL' => 'https://example.com/']);
    }

    public function testBannerPostReachesTheControllerThroughTheRoute()
    {
        $response = Director::test('/_consent/register', null, null, 'POST', $this->banner());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('cookie-banner', ConsentRecord::get()->first()->Source);
    }

    public function testOldRouteIsGone()
    {
        $this->assertSame(404, Director::test('/consent/register', null, null, 'POST', $this->banner())->getStatusCode());
    }

    private function runTask(array $vars): string
    {
        ob_start();
        (new ConsentRetentionTask())->run(new HTTPRequest('GET', '/', $vars));

        return trim(ob_get_clean());
    }

    public function testTaskNeedsAnOptionAndErasesByConsentID()
    {
        ConsentRecord::registerConsent(['Email' => 'a@example.com'], ['ConsentType' => 'X', 'URL' => 'https://example.com/']);
        ConsentRecord::registerConsent(['Email' => 'b@example.com'], ['ConsentType' => 'X', 'URL' => 'https://example.com/']);

        $this->assertStringContainsString('Give older-than-days', $this->runTask([]));
        $this->assertSame(2, ConsentRecord::get()->count());

        $this->assertStringContainsString('Deleted 1', $this->runTask(['consent-id' => 'a@example.com']));
        $this->assertSame(['b@example.com'], ConsentRecord::get()->column('ConsentID'));
    }
}

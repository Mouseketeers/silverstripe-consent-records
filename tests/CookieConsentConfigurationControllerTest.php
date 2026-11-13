<?php

namespace Mouseketeers\ConsentRecords\Tests;

use Mouseketeers\ConsentRecords\ConsentRecord;
use Mouseketeers\ConsentRecords\CookieConsentConfigurationController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

class CookieConsentConfigurationControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function post(array $payload, array $headers = []): array
    {
        $request = new HTTPRequest('POST', 'register', [], [], json_encode($payload));
        foreach ($headers as $name => $value) {
            $request->addHeader($name, $value);
        }
        $response = CookieConsentConfigurationController::create()->register($request);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    }

    private function cookieConsent(array $extra = []): array
    {
        return $extra + ['ConsentType' => 'CookieConsent', 'ConsentID' => 'abc', 'URL' => 'https://example.com/'];
    }

    public function testValidPayloadIsSaved()
    {
        [$status, $body] = $this->post($this->cookieConsent());

        $this->assertSame(200, $status);
        $this->assertSame('ok', $body['status']);
        $this->assertSame(1, ConsentRecord::get()->count());
    }

    public function testSourceIsSetByTheServer()
    {
        $this->post($this->cookieConsent(['Source' => 'userform', 'SubmissionID' => 5]));

        $record = ConsentRecord::get()->first();
        $this->assertSame('cookie-banner', $record->Source);
        $this->assertSame(0, (int) $record->SubmissionID);
    }

    public function testOtherConsentTypesAreRejected()
    {
        [$status] = $this->post($this->cookieConsent(['ConsentType' => 'TermsAndPrivacyConsent']));

        $this->assertSame(400, $status);
        $this->assertSame(0, ConsentRecord::get()->count());
    }

    public function testMultiConsentPayloadIsNotAccepted()
    {
        [$status] = $this->post(['Consents' => [$this->cookieConsent()]]);

        $this->assertSame(400, $status);
        $this->assertSame(0, ConsentRecord::get()->count());
    }

    public function testFormDataFromTheVisitorIsIgnored()
    {
        $this->post($this->cookieConsent(['FormData' => ['Email' => 'victim@example.com']]));

        $this->assertStringNotContainsString('victim', (string) ConsentRecord::get()->first()->ConsentData);
    }

    public function testCrossSiteRequestIsRejected()
    {
        [$status] = $this->post($this->cookieConsent(), ['Sec-Fetch-Site' => 'cross-site']);

        $this->assertSame(403, $status);
        $this->assertSame(0, ConsentRecord::get()->count());
    }

    public function testOversizedBodyIsRejected()
    {
        [$status] = $this->post($this->cookieConsent(['ConsentData' => str_repeat('a', 20000)]));

        $this->assertSame(413, $status);
    }

    public function testFormEncodedPostIsRejected()
    {
        $response = CookieConsentConfigurationController::create()
            ->register(new HTTPRequest('POST', 'register', [], ['payloadData' => '{}']));

        $this->assertSame(400, $response->getStatusCode());
    }
}

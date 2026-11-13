<?php

namespace Mouseketeers\ConsentRecords\Tests;

use Mouseketeers\ConsentRecords\ConsentRecord;
use Mouseketeers\ConsentRecords\CookieConsentConfigurationController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

class CookieConsentConfigurationControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function post(array $payload): array
    {
        $request = new HTTPRequest('POST', 'register', [], [], json_encode($payload));
        $response = CookieConsentConfigurationController::create()->register($request);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    }

    public function testValidPayloadIsSaved()
    {
        [$status, $body] = $this->post([
            'FormData' => ['Email' => 'jo@example.com'],
            'consent' => ['ConsentType' => 'Cookies', 'URL' => 'https://example.com/'],
        ]);

        $this->assertSame(200, $status);
        $this->assertSame('ok', $body['status']);
        $this->assertSame(1, ConsentRecord::get()->count());
    }

    public function testFormEncodedPostIsRejected()
    {
        $_POST['payloadData'] = json_encode(['consent' => ['ConsentType' => 'Cookies']]);

        try {
            $response = CookieConsentConfigurationController::create()
                ->register(new HTTPRequest('POST', 'register', [], $_POST));
        } finally {
            unset($_POST['payloadData']);
        }

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, ConsentRecord::get()->count());
    }

    public function testTooManyConsentsAreRejected()
    {
        $consents = array_fill(0, 21, ['ConsentType' => 'Cookies', 'URL' => 'https://example.com/']);

        [$status] = $this->post(['Consents' => $consents]);

        $this->assertSame(400, $status);
        $this->assertSame(0, ConsentRecord::get()->count());
    }

    public function testEmptyConsentsAreRejected()
    {
        [$status] = $this->post(['Consents' => []]);

        $this->assertSame(400, $status);
    }
}

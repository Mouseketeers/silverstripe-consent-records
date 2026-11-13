<?php

class CookieConsentControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testJsonPayloadIsSaved()
    {
        $request = new SS_HTTPRequest('POST', 'register', [], [], json_encode([
            'consent' => ['Email' => 'jo@example.com', 'ConsentType' => 'Cookies', 'URL' => 'https://example.com/'],
        ]));
        $response = CookieConsentController::create()->register($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, ConsentRecord::get()->count());
    }

    public function testFormEncodedPostIsRejected()
    {
        $_POST['payloadData'] = json_encode(['consent' => ['Email' => 'jo@example.com']]);

        try {
            $response = CookieConsentController::create()
                ->register(new SS_HTTPRequest('POST', 'register', [], $_POST));
        } finally {
            unset($_POST['payloadData']);
        }

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, ConsentRecord::get()->count());
    }
}

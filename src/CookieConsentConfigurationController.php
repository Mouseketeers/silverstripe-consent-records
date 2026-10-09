<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

class CookieConsentConfigurationController extends Controller
{
    private static $allowed_actions = [
        'register',
        'configuration'
    ];

    public function register(HTTPRequest $request)
    {
        if (!$request->isPOST()) {
            return $this->jsonResponse([
                'status' => 'ok',
                'message' => 'POST consent data to this endpoint.'
            ]);
        }

        $payload = $this->getRequestPayload($request);

        if (empty($payload)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Missing consent payload.'
            ], 400);
        }

        $formData = $payload['FormData'] ?? [];
        $consents = isset($payload['Consents']) && is_array($payload['Consents'])
            ? $payload['Consents']
            : [$payload['consent'] ?? $payload];

        foreach ($consents as $consent) {
            if (!ConsentRecord::registerConsent($formData, $consent)) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Consent could not be saved.'
                ], 500);
            }
        }

        return $this->jsonResponse([
            'status' => 'ok'
        ]);
    }

    public function configuration(HTTPRequest $request)
    {
        return $this->jsonResponse([
            'status' => 'ok'
        ]);
    }

    protected function getRequestPayload(HTTPRequest $request)
    {
        $body = trim((string) $request->getBody());

        if ($body !== '') {
            $payload = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($payload)) {
                return $payload;
            }
        }

        if (isset($_POST['payloadData'])) {
            $payload = json_decode($_POST['payloadData'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($payload)) {
                return $payload;
            }
        }

        return [];
    }

    protected function jsonResponse(array $data, $statusCode = 200)
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setStatusCode($statusCode);
        $response->setBody(json_encode($data));

        return $response;
    }
}

if (!class_exists('CookieConsentConfigurationController', false)) {
    class_alias(__NAMESPACE__ . '\\CookieConsentConfigurationController', 'CookieConsentConfigurationController');
}
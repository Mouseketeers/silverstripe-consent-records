<?php

class CookieConsentController extends Controller
{
    private static $allowed_actions = [
        'register'
    ];

    public function register(SS_HTTPRequest $request)
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

        if (isset($payload['Consents']) && is_array($payload['Consents'])) {
            $formData = $payload['FormData'] ?? [];
            foreach ($payload['Consents'] as $consent) {
                ConsentRecord::registerConsent($formData, $consent);
            }
        } else {
            $consentData = $payload['consent'] ?? $payload;
            $formData = $payload['FormData'] ?? [];
            ConsentRecord::registerConsent($formData, $consentData);
        }

        return $this->jsonResponse([
            'status' => 'ok'
        ]);
    }

    protected function getRequestPayload(SS_HTTPRequest $request)
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
        $response = new SS_HTTPResponse();
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setStatusCode($statusCode);
        $response->setBody(json_encode($data));

        return $response;
    }
}
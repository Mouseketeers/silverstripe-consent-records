<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Public endpoint the cookie banner posts its consent choice to.
 *
 * It is unauthenticated, so it only accepts a single consent of an allowed
 * type, from the same origin. Multi-consent writes and form data are for
 * server-side callers of {@link ConsentRecord::registerConsents()}.
 */
class CookieConsentConfigurationController extends Controller
{
    /**
     * Consent types the public endpoint may write. Anything else is rejected,
     * so the endpoint cannot be used to forge e.g. terms or newsletter consents.
     *
     * @config
     */
    private static $allowed_types = ['CookieConsent'];

    /**
     * Largest request body accepted, in bytes.
     *
     * @config
     */
    private static $max_body_bytes = 10000;

    /**
     * Keep in step with the rule in _config/routes.yml.
     *
     * @config
     */
    private static $url_segment = '_consent';

    private static $allowed_actions = [
        'register'
    ];

    /** The only consent properties a visitor may set. */
    private const CLIENT_FIELDS = ['ConsentType', 'ConsentID', 'ConsentStatement', 'ConsentData', 'URL'];

    public function Link($action = null)
    {
        return Controller::join_links($this->config()->get('url_segment'), $action);
    }

    public function register(HTTPRequest $request)
    {
        if (!$request->isPOST()) {
            return $this->jsonResponse([
                'status' => 'ok',
                'message' => 'POST consent data to this endpoint.'
            ]);
        }

        if (!$this->isSameOrigin($request)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $body = (string) $request->getBody();

        if (strlen($body) > $this->config()->get('max_body_bytes')) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Payload too large.'], 413);
        }

        $payload = $this->decode($body);

        if (empty($payload)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Missing consent payload.'], 400);
        }

        $consent = array_intersect_key($payload, array_flip(self::CLIENT_FIELDS));

        if (!in_array($consent['ConsentType'] ?? null, $this->config()->get('allowed_types'), true)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Consent type not allowed.'], 400);
        }

        // Set by the server so it can be trusted; the visitor cannot choose it.
        $consent['Source'] = 'cookie-banner';

        // Scalars only: an array here would be stored as JSON of the visitor's choosing.
        foreach ($consent as $value) {
            if (!is_scalar($value)) {
                return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid consent payload.'], 400);
            }
        }

        if (!ConsentRecord::registerConsents(['Consents' => [$consent]])) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Consent could not be saved.'], 500);
        }

        return $this->jsonResponse(['status' => 'ok']);
    }

    /**
     * Browsers send Sec-Fetch-Site and Origin on cross-site posts; reject those.
     * Non-browser clients can still forge the headers, which is why the type
     * allowlist above is the main protection.
     */
    protected function isSameOrigin(HTTPRequest $request): bool
    {
        $site = $request->getHeader('Sec-Fetch-Site');
        if ($site !== null && $site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }

        $origin = $request->getHeader('Origin');
        if ($origin !== null && $origin !== '') {
            return parse_url($origin, PHP_URL_HOST) === parse_url(Director::absoluteBaseURL(), PHP_URL_HOST);
        }

        return true;
    }

    protected function decode(string $body): array
    {
        $payload = json_decode(trim($body), true);

        return is_array($payload) ? $payload : [];
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

# SilverStripe Consent Records Module

A SilverStripe module for managing user consent records to help with GDPR compliance.

## Features

- Store user consent records with detailed information
- Track consent statements, data, and timestamps
- Link to specific legal pages (Terms of Use, Privacy Policy)
- Admin interface for viewing and managing consent records
- Site configuration integration for legal page management

## Installation

```bash
composer require mouseketeers/silverstripe-consent-records
```

## Usage

### Recording Consent

```php
use Mouseketeers\ConsentRecords\ConsentRecord;

// Simple consent recording (the statement is 'N/A' unless you pass one)
$data = [
    'Email' => 'user@example.com',
];

ConsentRecord::registerConsent($data, [
    'ConsentStatement' => 'I agree to the terms and privacy policy',
]);

// Advanced consent recording with custom consent data
$consent = [
    'ConsentType' => 'Newsletter',
    'ConsentID' => 'newsletter_signup_2024',
    'ConsentStatement' => 'I agree to receive newsletters',
    'ConsentData' => ['source' => 'homepage', 'campaign' => 'winter2024']
];

ConsentRecord::registerConsent($data, $consent);
```

### Multiple Consents

```php
$data = [
    'FormData' => ['Name' => 'John Doe', 'Email' => 'john@example.com'],
    'Consents' => [
        [
            'ConsentType' => 'Newsletter',
            'ConsentStatement' => 'I agree to receive newsletters'
        ],
        [
            'ConsentType' => 'Marketing',
            'ConsentStatement' => 'I agree to marketing communications'
        ]
    ]
];

ConsentRecord::registerConsents($data);
```

## Configuration

The module extends SiteConfig to allow you to link legal pages. Go to Settings > Legal Pages in the CMS to configure:

- Terms Page
- Privacy Policy Page

These pages will be automatically linked to consent records for audit purposes.

### Default legal page URLs

When no page is selected in Site Settings, `getTermsPageLink()` and `getPrivacyPageLink()` fall back to a configured URL. This is useful when the legal pages live outside SilverStripe, or before a page has been selected:

```yml
---
Name: my-consent-defaults
After: '#consent-records'
---
Mouseketeers\ConsentRecords\ConsentRecordsSiteConfigExtension:
  terms_page_url: "https://example.com/terms-and-conditions"
  privacy_page_url: "https://example.com/privacy-policy"
```

The configured URL is only used when no page is selected, and the label passed to the method is used as the link text:

```php
$siteConfig = SiteConfig::current_site_config();

$termsLink = $siteConfig->getTermsPageLink('Terms of Use');
$privacyLink = $siteConfig->getPrivacyPageLink('Privacy Policy');
```

## Cookie banner endpoint

`POST /_consent/register` (used by mouseketeers/silverstripe-cookie-consent) accepts **one** consent as JSON
(`ConsentType`, `ConsentID`, `ConsentStatement`, `ConsentData`, `URL`). Only types in
`CookieConsentConfigurationController.allowed_types` (default `CookieConsent`) are accepted, cross-site
browser requests are rejected, and the body is capped at `max_body_bytes`. Form data and multi-consent
writes are only possible server-side through `ConsentRecord::registerConsents()`.

## Module compatibility

| consent-records | consent-forms | consent-userforms | cookie-consent | SilverStripe |
|-----------------|---------------|-------------------|----------------|--------------|
| 2.2+            | 3.x           | 2.x               | 2.x, 3.x       | 4            |

`cookie-consent` needs consent-records 2.2 or later (the `_consent/register` route). Earlier versions expose
`/consent/register`. The retention/erasure task is only available on the SilverStripe 6 line (5.x).

## Database Fields

- `ConsentID`: Unique identifier for the consent
- `ConsentStatement`: The actual consent text (HTML stripped)
- `ConsentData`: Additional data stored as JSON
- `ConsentType`: Type of consent (e.g., 'TermsAndPrivacyConsent', 'Newsletter')
- `Source`: What wrote the record (`cookie-banner`, `userform`, …), set by the server
- `SubmissionID`: ID of the originating submission, e.g. a UserForms `SubmittedForm` (0 if none)
- `URL`: The URL where consent was given
- `TermsPageID/Version`: Link to Terms page at time of consent
- `PrivacyPageID/Version`: Link to Privacy page at time of consent

## Requirements

- SilverStripe Framework ^4.0
- SilverStripe CMS ^4.0
- SilverStripe Admin ^1.0

## License

BSD-3-Clause

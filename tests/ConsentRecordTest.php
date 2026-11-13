<?php

namespace Mouseketeers\ConsentRecords\Tests;

use Mouseketeers\ConsentRecords\ConsentRecord;
use SilverStripe\Dev\SapphireTest;

class ConsentRecordTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRegisterConsentStoresTheFormData()
    {
        $id = ConsentRecord::registerConsent(
            ['Name' => 'Jo', 'Email' => 'jo@example.com'],
            ['ConsentType' => 'Newsletter', 'ConsentStatement' => 'I agree', 'URL' => 'https://example.com/']
        );

        $record = ConsentRecord::get()->byID($id);

        $this->assertSame('jo@example.com', $record->ConsentID);
        $this->assertSame(['Name' => 'Jo', 'Email' => 'jo@example.com'], json_decode($record->ConsentData, true));
    }

    public function testConsentDataIsNotChangedByTheHtmlCleaner()
    {
        $id = ConsentRecord::registerConsent(
            ['Email' => 'jo@example.com'],
            ['ConsentData' => '{"note":"<b>yes"}', 'URL' => 'https://example.com/']
        );

        $this->assertSame('{"note":"<b>yes"}', ConsentRecord::get()->byID($id)->ConsentData);
    }

    public function testLongConsentStatementIsSaved()
    {
        $statement = str_repeat('a', 400);
        $id = ConsentRecord::registerConsent(
            ['Email' => 'jo@example.com'],
            ['ConsentStatement' => $statement, 'URL' => 'https://example.com/']
        );

        $this->assertSame($statement, ConsentRecord::get()->byID($id)->ConsentStatement);
    }

    public function testRegisterConsentsSavesAll()
    {
        $this->assertTrue(ConsentRecord::registerConsents([
            'FormData' => ['Email' => 'jo@example.com'],
            'Consents' => [
                ['ConsentType' => 'Newsletter', 'URL' => 'https://example.com/'],
                ['ConsentType' => 'Marketing', 'URL' => 'https://example.com/'],
            ],
        ]));

        $this->assertSame(2, ConsentRecord::get()->count());
    }

    public function testRegisterConsentsWithoutConsentsSavesNothingAndReportsIt()
    {
        $this->assertFalse(ConsentRecord::registerConsents(['FormData' => ['Email' => 'jo@example.com']]));
        $this->assertSame(0, ConsentRecord::get()->count());
    }
}

<?php

class ConsentRecordTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testConsentStatementCanHoldLongText()
    {
        $fields = DataObject::database_fields('ConsentRecord');

        $this->assertSame('Text', $fields['ConsentStatement']);
    }

    public function testConsentDataIsNotChangedByTheHtmlCleaner()
    {
        $id = ConsentRecord::registerConsent([
            'Email' => 'jo@example.com',
            'ConsentData' => '{"note":"<b>yes"}',
            'URL' => 'https://example.com/',
        ]);

        $this->assertSame('{"note":"<b>yes"}', ConsentRecord::get()->byID($id)->ConsentData);
    }

    public function testConsentStatementIsStrippedOfHtml()
    {
        $id = ConsentRecord::registerConsent([
            'Email' => 'jo@example.com',
            'ConsentStatement' => 'I <b>agree</b>',
            'URL' => 'https://example.com/',
        ]);

        $this->assertSame('I agree', ConsentRecord::get()->byID($id)->ConsentStatement);
    }
}

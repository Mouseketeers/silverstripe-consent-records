<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;


class ConsentRecord extends DataObject {

	private static $table_name = 'ConsentRecord';

	private static $default_sort = "Created DESC";
	
	private static $db = [
		'ConsentID' => 'Varchar(255)',
		'ConsentStatement' => 'Varchar(255)',
		'ConsentData' => 'Text',
		'ConsentType' => 'Varchar(255)',
		'URL' => 'Varchar(255)',
		'TermsPageID' => 'Int',
		'TermsPageVersion' => 'Int',
		'PrivacyPageID' => 'Int',
		'PrivacyPageVersion' => 'Int'
	];

	private static $summary_fields = [
		'Created',
		'ConsentID',
		'ConsentStatement',
		'ConsentType',
		'URL'
	];
	private static $searchable_fields = [
		'ConsentID',
		'ConsentStatement',
		'ConsentType',
		'URL'
	];
	private static $indexes = [
		'ConsentID' => true
	];
	
	public static function registerConsents($data) 
	{
		foreach ($data['Consents'] as $consent) {
			self::registerConsent($data['FormData'], $consent);
		}
	}

	public static function registerConsent($data, $consent = null)
	{
		$consent = $consent ?? [];
		$consentRecord = new ConsentRecord();

		$assignments = [
			'ConsentType' => 		$consent['ConsentType'] ?? null,
			'ConsentID' => 			$consent['ConsentID'] ?? $data['Email'] ?? null,
			'ConsentStatement' => 	$consent['ConsentStatement'] ?? $data['TermsAndPrivacyConsent'] ?? null,
			'ConsentData' => 		$consent['ConsentData'] ?? $data['FormData'] ?? null,
			'URL' => 				$consent['URL'] ?? Director::absoluteURL(Controller::curr()->getRequest()->getURL())
		];

		if (!empty($assignments['ConsentData']) && is_array($assignments['ConsentData'])) {
			$assignments['ConsentData'] = json_encode($assignments['ConsentData']);
		}

		foreach ($assignments as $property => $value) {
			if (!empty($value)) {
				$consentRecord->$property = $value;
			}
		}

		$consentRecord->write();
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();

		if ($this->ConsentStatement) {
			$this->ConsentStatement = strip_tags($this->ConsentStatement);
		}

		if ($this->ConsentType == 'TermsAndPrivacyConsent') {
			$siteConfig = SiteConfig::current_site_config();
			
			if ($siteConfig->TermsPageID && $siteConfig->TermsPage()) {
				$this->TermsPageID = $siteConfig->TermsPageID;
				$this->TermsPageVersion = $siteConfig->TermsPage()->Version ?? 0;
			}
			if ($siteConfig->PrivacyPageID && $siteConfig->PrivacyPage()) {
				$this->PrivacyPageID = $siteConfig->PrivacyPageID;
				$this->PrivacyPageVersion = $siteConfig->PrivacyPage()->Version ?? 0;
			}
		}
	}
}
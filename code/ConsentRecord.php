<?php

class ConsentRecord extends DataObject
{

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
		'ConsentID'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		
		// Make all fields readonly
		foreach($fields->dataFields() as $field) {
			$readonlyField = $field->performReadonlyTransformation();
			$fields->replaceField($field->getName(), $readonlyField);
		}
		
		return $fields;
	}

	public static function registerConsents($data) 
	{
		foreach($data['Consents'] as $consent) {
			self::registerConsent($data['FormData'], $consent);
		}
	}
	public static function registerConsent($data, $consent = null)
	{
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
		if ($this->ConsentData) {
			$this->ConsentData = strip_tags($this->ConsentData);
		}

		$siteConfig = SiteConfig::current_site_config();		
		
		if ($siteConfig->TermsPageID) {
			$this->TermsPageID = $siteConfig->TermsPageID;
			$this->TermsPageVersion = $siteConfig->TermsPage()->Version ?? 0;
		}
		if ($siteConfig->PrivacyPageID) {
			$this->PrivacyPageID = $siteConfig->PrivacyPageID;
			$this->PrivacyPageVersion = $siteConfig->PrivacyPage()->Version ?? 0;
		}
	}
}
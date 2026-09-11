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

	public static function registerConsent($consentData)
	{
		$assignments = [
			'ConsentID' =>          $consentData['ConsentID'] ?? $consentData['Email'] ?? 'N/A',
			'ConsentType' =>        $consentData['ConsentType'] ?? 'N/A',
			'ConsentStatement' =>   $consentData['ConsentStatement'] ?? $consentData['TermsAndPrivacyConsent'] ?? 'N/A',
			'ConsentData' =>        $consentData['ConsentData'] ?? 'N/A',
			'URL' =>                $consentData['URL'] ?? Director::absoluteURL(Controller::curr()->getRequest()->getURL())
		];

		// Convert ConsentData to JSON if it's an array
		if (!empty($assignments['ConsentData']) && is_array($assignments['ConsentData'])) {
			$assignments['ConsentData'] = json_encode($assignments['ConsentData']);
		}

    	$consentRecord = new ConsentRecord();

		foreach ($assignments as $property => $value) {
        	$consentRecord->$property = $value; // Direct assignment without checking emptiness
		}
		return $consentRecord->write();
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

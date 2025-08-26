<?php
class ConsentRecordsSiteConfigExtension extends DataExtension {

	private static $has_one = [
		'TermsPage' => 'SiteTree',
        'PrivacyPage' => 'SiteTree'
	];
	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldsToTab("Root.LegalPages", 
			array(
                TreeDropdownField::create('TermsPageID', 'Terms Page', 'SiteTree'),
                TreeDropdownField::create('PrivacyPageID', 'Privacy Policy Page', 'SiteTree')
			)
		);	
	}
}
<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\CMS\Model\SiteTree;

class ConsentRecordsSiteConfigExtension extends DataExtension 
{
    private static $has_one = [
        'TermsPage' => SiteTree::class,
        'PrivacyPage' => SiteTree::class
    ];

    public function updateCMSFields(FieldList $fields) 
    {
        $fields->addFieldsToTab("Root.LegalPages", [
            TreeDropdownField::create('TermsPageID', 'Terms Page', SiteTree::class),
            TreeDropdownField::create('PrivacyPageID', 'Privacy Policy Page', SiteTree::class)
        ]);	
    }
}
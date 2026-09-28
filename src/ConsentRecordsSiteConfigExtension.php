<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\CMS\Model\SiteTree;

class ConsentRecordsSiteConfigExtension extends DataExtension 
{
    /**
     * Default terms page URL. Used when no Terms page is selected in Site Settings.
     *
     * @config
     */
    private static $terms_page_url = '';

    /**
     * Default privacy page URL. Used when no Privacy page is selected in Site Settings.
     *
     * @config
     */
    private static $privacy_page_url = '';

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

    /**
     * Link to the terms page, falling back to the configured terms_page_url.
     *
     * @param string $label Link text used for the configured fallback URL
     * @return string Anchor tag, or an empty string when no page or fallback URL is set
     */
    public function getTermsPageLink($label = null)
    {
        $page = $this->owner->TermsPageID ? $this->owner->TermsPage() : null;

        return $this->buildLegalPageLink($page, Config::inst()->get(self::class, 'terms_page_url'), $label);
    }

    /**
     * Link to the privacy page, falling back to the configured privacy_page_url.
     *
     * @param string $label Link text used for the configured fallback URL
     * @return string Anchor tag, or an empty string when no page or fallback URL is set
     */
    public function getPrivacyPageLink($label = null)
    {
        $page = $this->owner->PrivacyPageID ? $this->owner->PrivacyPage() : null;

        return $this->buildLegalPageLink($page, Config::inst()->get(self::class, 'privacy_page_url'), $label);
    }

    /**
     * Builds an anchor to a legal page. The page title is used as link text when a
     * page is selected, the given label is used for the configured fallback URL.
     *
     * @param SiteTree|null $page
     * @param string $url
     * @param string $label
     * @return string
     */
    protected function buildLegalPageLink($page, $url, $label = null)
    {
        if ($page && $page->exists()) {
            return sprintf(
                '<a href="%s" target="_blank" class="legal-page-link">%s</a>',
                $page->Link(),
                $page->MenuTitle
            );
        }

        if ($url) {
            return sprintf(
                '<a href="%s" target="_blank" class="legal-page-link">%s</a>',
                $url,
                $label
            );
        }

        return '';
    }
}

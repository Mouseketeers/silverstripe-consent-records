<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\ORM\DataObject;


class ConsentRecord extends DataObject {

	private static $table_name = 'ConsentRecord';

	private static $default_sort = "Created DESC";
	
	private static $db = [
		'ConsentID' => 'Varchar(255)',
		'ConsentStatement' => 'Text',
		'ConsentData' => 'Varchar(255)',
		'ConsentType' => 'Varchar(255)',
		'URL' => 'Varchar(255)'
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
		'ConsentRecordIndex' => ['ConsentID']
	];
}
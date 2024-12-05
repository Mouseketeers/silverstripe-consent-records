<?php

class ConsentRecord extends DataObject {

	private static $default_sort = "Created DESC";
	
	private static $db = array(
		'ConsentID' => 'Varchar(255)',
		'ConsentStatement' => 'Text',
		'ConsentData' => 'Varchar(255)',
		'ConsentType' => 'Varchar(255)',
		'URL' => 'Varchar(255)'
	);
	private static $summary_fields = array(
		'Created',
		'ConsentID',
		'ConsentStatement',
		'ConsentType',
		'URL'
	);
	private static $searchable_fields = array(
		'ConsentID',
		'ConsentStatement',
		'ConsentType',
		'URL'
	);	
	private static $indexes = array(
		'ConsentID'
	);
}
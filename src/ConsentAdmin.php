<?php

namespace Mouseketeers\ConsentRecords;

use SilverStripe\Admin\ModelAdmin;
use Mouseketeers\ConsentRecords\ConsentRecord;

class ConsentAdmin extends ModelAdmin {

	private static $menu_icon_class = 'font-icon-edit-list';
	
	private static $managed_models = [
		ConsentRecord::class
	];
	
	private static $url_segment = 'consents';

	private static $menu_title = 'User Consents';

	public function subsiteCMSShowInMenu() {
		return true;
	}
}
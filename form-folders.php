<?php
/**
 * Plugin Name: Form Folders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


add_action(
	'gform_loaded',
	function () {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}
		require_once 'includes/class-form-folders.php';

		GFAddOn::register( 'Form_Folders' );
	}
);

define( 'FORM_FOLDERS_VERSION', '1.0.0' );
define( 'FORM_FOLDERS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Returns the instance of the Form_Folders class.
 *
 * @return Form_Folders|null
 */
function form_folders() {
	if ( class_exists( 'Form_Folders' ) ) {
		return Form_Folders::get_instance();
	}
	return null;
}

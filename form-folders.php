<?php
/**
 * Plugin Name: Form Folders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( function_exists( 'register_form_folders_submenu' ) ) {
	add_action(
        'admin_notices',
        function () {
			echo '<div class="notice notice-error"><p>The Form Folders snippet version has been detected. Please deactivate it before using this plugin.</p></div>';
		}
        );

	return;
}

add_action(
	'gform_loaded',
	function () {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}
		require_once 'includes/class-form-folders.php';

		GFAddOn::register( 'Form_Folders' );

		// Load Views Folders class if GravityView is active
		if ( class_exists( 'GVCommon' ) ) {
			require_once 'includes/class-views-folders.php';
			GFAddOn::register( 'Views_Folders' );
		}
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

/**
 * Returns the instance of the Views_Folders class.
 *
 * @return Views_Folders|null
 */
function views_folders() {
	if ( class_exists( 'Views_Folders' ) ) {
		return Views_Folders::get_instance();
	}
	return null;
}

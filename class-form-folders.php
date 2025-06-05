<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

GFForms::include_addon_framework();

/**
 * Class Form_Folders
 *
 * This class extends the GFAddOn and is responsible for handling the Form Folders for Gravity Forms plugin.
 */
class Form_Folders extends GFAddOn {


	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
	/**
	 * The current version of the plugin
	 *
	 * @var string
	 */
	protected $_version = FORM_FOLDERS_VERSION;
	/**
	 * A string representing the slug used for the plugin.
	 *
	 * @var string
	 */
	protected $_slug = 'form-folders';
	/**
	 * The basename path of the plugin
	 *
	 * @var string
	 */
	protected $_path = FORM_FOLDERS_BASENAME;
	/**
	 * The full file path of the current script.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;
	/**
	 * The full title of the plugin
	 *
	 * @var string
	 */
	protected $_title = 'Form Folders for Gravity Forms';
	/**
	 * The short title of the plugin.
	 *
	 * @var string
	 */
	protected $_short_title = 'Form Folders';
	/**
	 * Holds a list of capabilities.
	 *
	 * @var array
	 */
	protected $_capabilities = [ 'gf_form_folders_uninstall' ];
	/**
	 * Holds the capability required for uninstallation.
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'gf_form_folders_uninstall';
	/**
	 * Holds the singleton instance of the class.
	 *
	 * @var self|null
	 */
	private static ?self $_instance = null;
	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Returns the singleton instance of this class.
	 *
	 * This method ensures that only one instance of the class is created.
	 * If the instance does not yet exist, it is created; otherwise,
	 * the existing instance is returned.
	 *
	 * @return self|null The singleton instance of the class.
	 */
	public static function get_instance(): ?self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Initializes the class by adding necessary filters.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();
		$this->register_form_folders_taxonomy();

		add_action( 'wp_ajax_create_folder', [ $this, 'handle_create_folder' ] );
		add_action( 'wp_ajax_assign_form_to_folder', [ $this, 'handle_assign_form_to_folder' ] );
		add_action( 'wp_ajax_remove_form_from_folder', [ $this, 'handle_remove_form_from_folder' ] );
		add_action( 'wp_ajax_rename_folder', [ $this, 'handle_folder_renaming' ] );
		add_action( 'wp_ajax_delete_folder', [ $this, 'handle_folder_deletion' ] );
	}

	/**
	 * Initializes the admin functionality of the plugin.
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'admin_menu', [ $this, 'register_form_folders_submenu' ], 15 );
	}

	/**
	 * Registers a submenu page under the Gravity Forms menu for form folders.
	 *
	 * @return void
	 */
	public function register_form_folders_submenu() {
		add_submenu_page(
			'gf_edit_forms',
			'Form Folders',
			'Form Folders',
			'gform_full_access',
			'gf-form-folders',
			[ $this, 'form_folders_page' ]
		);
	}
	/**
	 * Registers a custom taxonomy for organizing forms into folders.
	 *
	 * The taxonomy 'gf_form_folders' is associated with the 'gf_form' post type. It is not publicly queryable,
	 * does not have URL rewrites, and supports a non-hierarchical structure. It includes an admin column for easier management in the admin interface.
	 *
	 * @return void
	 */
	private function register_form_folders_taxonomy() {
		register_taxonomy(
			'gf_form_folders',
			'gf_form',
			[
				'label'             => 'Form Folders',
				'rewrite'           => false,
				'public'            => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
			]
		);
	}

	/**
	 * Handles the creation of a new folder for forms.
	 *
	 * Validates the current user's permission and the provided folder name.
	 * Inserts a new term into the 'gf_form_folders' taxonomy. Returns a success or error message depending on the outcome.
	 *
	 * @return void Sends a JSON response indicating success or failure.
	 */
	public function handle_create_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'create_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folder_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Folder name is required' ], 403 );
			wp_die();
		}

		$folder_name = sanitize_text_field( wp_unslash( $_POST['folder_name'] ) );
		$inserted    = wp_insert_term( $folder_name, 'gf_form_folders' );

		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( [ 'message' => $inserted->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Folder created successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the process of assigning a form to a folder.
	 *
	 * Ensures the current user has the necessary permissions to perform the action.
	 * Validates required input data, assigns the form to the specified folder,
	 * and returns the appropriate success or error messages.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 */
	public function handle_assign_form_to_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assign_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
			wp_die();
		}

		if ( empty( $_POST['form_id'] ) || empty( $_POST['folder_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Form and Folder are required' ] );
			wp_die();
		}

		$form_id   = absint( $_POST['form_id'] );
		$folder_id = absint( $_POST['folder_id'] );

		$result = wp_set_object_terms( $form_id, [ $folder_id ], 'gf_form_folders' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Form assigned successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the removal of a form from a folder.
	 *
	 * This function validates the nonce, checks user permissions, and removes the specified form
	 * from its associated folder. It sends a JSON response indicating success or failure.
	 *
	 * @return void Outputs a JSON response and terminates execution.
	 */
	public function handle_remove_form_from_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'remove_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['form_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Form ID is required' ], 403 );
			wp_die();
		}

		$form_id = absint( $_POST['form_id'] );

		$result = wp_set_object_terms( $form_id, [], 'gf_form_folders' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Form removed from the folder successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the renaming of a folder via an AJAX request.
	 *
	 * This function validates the provided nonce, ensures required parameters
	 * are present, and updates the folder name in the taxonomy. Errors are returned
	 * in JSON format, and a success response is sent upon successful renaming.
	 *
	 * @return void This function exits with a JSON response and does not return.
	 */
	public function handle_folder_renaming() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rename_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folder_id'] ) || empty( $_POST['folder_name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'my-textdomain' ) ], 400 );
			wp_die();
		}

		$folder_id   = absint( $_POST['folder_id'] );
		$folder_name = sanitize_text_field( wp_unslash( $_POST['folder_name'] ) );

		$folder = get_term( $folder_id, 'gf_form_folders' );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => __( 'The specified folder does not exist.', 'my-textdomain' ) ], 404 );
		}

		// Update the folder name
		$updated_folder = wp_update_term( $folder_id, 'gf_form_folders', [ 'name' => $folder_name ] );
		if ( is_wp_error( $updated_folder ) ) {
			wp_send_json_error( [ 'message' => __( 'Failed to rename the folder. Please try again.', 'my-textdomain' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Folder renamed successfully.', 'my-textdomain' ) ] );
		wp_die();
	}

	/**
	 * Deletes a folder via an AJAX request.
	 *
	 * @return void
	 */
	public function handle_folder_deletion() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}
		if ( empty( $_POST['folder_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
		}
		$folder_id = absint( $_POST['folder_id'] );
		$folder    = get_term( $folder_id, 'gf_form_folders' );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => 'The specified folder does not exist.' ], 404 );
		}
		$result = wp_delete_term( $folder_id, 'gf_form_folders' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => 'Failed to delete the folder. Please try again.' ], 403 );
		} else {
			wp_send_json_success( [ 'message' => 'Folder deleted successfully.' ] );
		}
	}

	/**
	 * Loads stylesheets for the plugin
	 *
	 * @return array
	 */
	public function styles() {
		$styles = [
			[
				'handle'  => 'form-folders-styles',
				'src'     => plugins_url( 'assets/css/folders_stylesheet.css', $this->_full_path ),
				'version' => '1.0.0',
				'enqueue' => [
					[ 'query' => 'page=gf-form-folders' ],
				],
			],
		];
		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Renders the Form Folders admin page for the Gravity Forms plugin.
	 *
	 * This method displays the main "Form Folders" page or a detailed view of a specific folder
	 * with its assigned forms. Includes functionality for viewing forms within a folder, creating
	 * new folders, and assigning forms to folders. Access is restricted to users with full Gravity Forms access.
	 *
	 * @return void
	 */
	public function form_folders_page() {
		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

        wp_enqueue_style( 'wp-admin-common', admin_url( 'load-styles.php?c=1&dir=ltr&load=common' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		if ( rgget( 'folder_id' ) ) {
			$this->render_single_folder_page();
		} else {
			$this->render_form_folders_page();
		}
	}

	/**
	 * Renders a single folder page with its assigned forms.
	 *
	 * @return void
	 */
	private function render_single_folder_page() {

		if ( ! isset( $_GET['view_folder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['view_folder_nonce'] ) ), 'view_folder' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}
		$folder_id = isset( $_GET['folder_id'] ) ? absint( $_GET['folder_id'] ) : 0;

		if ( $folder_id ) {
			$folder = get_term( $folder_id, 'gf_form_folders' );
			if ( is_wp_error( $folder ) || ! $folder ) {
				echo '<div class="error"><p>Invalid folder.</p></div>';
				return;
			}
			?>

			<div class="wrap">
				<h1>Forms in Folder: <?php echo esc_html( $folder->name ); ?> </h1>
				<!--Back button-->
				<br>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gf-form-folders' ) ); ?>" class="button">
					Back to All Folders
				</a>
				<br><br>

				<!--Forms Table-->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Form Name</th>
							<th>Shortcode</th>
							<th>Settings</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>

						<?php
						$forms                 = GFAPI::get_forms();
						$found                 = false;
						$allowed_svg_tags      = [
							'svg'  => [
								'xmlns'             => true,
								'viewbox'           => true,
								'width'             => true,
								'height'            => true,
								'style'             => true,
								'class'             => true,
								'enable-background' => true,
							],
							'g'    => [
								'fill'            => true,
								'stroke'          => true,
								'stroke-linecap'  => true,
								'stroke-linejoin' => true,
								'stroke-width'    => true,
							],
							'path' => [
								'd'         => true,
								'fill'      => true,
								'stroke'    => true,
								'fill-rule' => true,
							],
						];
						$post_html             = wp_kses_allowed_html( 'post' );
						$combined_allowed_html = array_merge_recursive( $post_html, $allowed_svg_tags );
						$remove_form_nonce     = wp_create_nonce( 'remove_form' );

						foreach ( $forms as $form ) {
							$form_terms = wp_get_object_terms( $form['id'], 'gf_form_folders', [ 'fields' => 'ids' ] );

							$edit_form_link = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form['id'] );

							if ( in_array( $folder_id, $form_terms, true ) ) {
								$found = true;
								?>
								<tr>
									<!--Form Title-->
									<td>
										<a href="<?php echo esc_url( $edit_form_link ); ?>"><?php echo esc_html( $form['title'] ); ?></a>
									</td>
									<!--Shortcode-->
									<td>
										<code class="copyable">
											[gravityform id="<?php echo esc_attr( $form['id'] ); ?>" title="false" description="false"]
										</code>
									</td>
									<!--Links-->
									<?php $this->render_links_td_section( $form, $allowed_svg_tags, $combined_allowed_html ); ?>
									<!--Buttons-->
									<td>
										<button class="remove-form" onclick="remove_form(<?php echo esc_attr( $form['id'] ) . ', \'' . esc_attr( $remove_form_nonce ) . '\''; ?>);">
											Remove
										</button>
									</td>
								</tr>
								<?php
							}
						}

						if ( ! $found ) {
							echo '<tr><td colspan="4">No forms found in this folder.</td></tr>';
						}
						$rename_folder_nonce = wp_create_nonce( 'rename_folder' );
						?>
					</tbody>
				</table>
				<br><br>

				<!--<h2>Rename Folder</h2>-->
				<form id="rename-folder-form">
					<label for="folder_name" style="font-size: 1.5em; font-weight: bold; margin-bottom: 0.5em; display: inline-block;">Rename Folder</label><br>
					<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $rename_folder_nonce ); ?>">
					<button type="submit">Rename Folder</button>
				</form>

				<script>
					document.addEventListener('DOMContentLoaded', function() {
						// Enable hover functionality
						document.querySelectorAll('.dropdown').forEach(function(dropdown) {
							const link = dropdown.querySelector('.link');
							const menu = dropdown.querySelector('.dropdown-menu');

							// Show dropdown on hover
							link.addEventListener('mouseover', function() {
								menu.style.display = 'block';
							});

							menu.addEventListener('mouseover', function() {
								menu.style.display = 'block';
							});

							// Hide dropdown when the mouse leaves
							dropdown.addEventListener('mouseleave', function() {
								menu.style.display = 'none';
							});
						});

						function handleFormSubmission(formId, action) {
							document.getElementById(formId).addEventListener('submit', function(e) {
								e.preventDefault();

								let formData = new FormData(this);
								formData.append('action', action);

								fetch(ajaxurl, {
										method: 'POST',
										body: formData
									})
									.then(response => response.json())
									.then(() => location.reload());
							});
						};
						handleFormSubmission('rename-folder-form', 'rename_folder');
						remove_form = function(formID, nonce) {
							const body = `action=remove_form_from_folder&form_id=${encodeURIComponent(formID)}&nonce=${encodeURIComponent(nonce)}`;

							fetch(ajaxurl, {
									method: 'POST',
									headers: {
										'Content-Type': 'application/x-www-form-urlencoded', // Specify the correct content type
									},
									body,
								})
								.then(response => response.json())
								.then(() => location.reload())
								.catch(error => console.error('Error:', error));
						};
						document.querySelectorAll(".copyable").forEach(function(element) {
							element.addEventListener("click", function() {
								navigator.clipboard.writeText(element.innerHTML);
								element.style.backgroundColor = "#d4edda"; // Light green to indicate success
								setTimeout(() => {
									element.style.backgroundColor = ""; // Revert after a short delay
								}, 1000);
							});
						});
					});
				</script>

			<?php
			echo '</div>';
		}
	}

	/**
	 * Renders the "Links" section of the table for a specific form in the folder.
	 *
	 * @param array $form The current form.
	 * @param array $allowed_svg_tags A list of allowed SVG tags.
	 * @param array $combined_allowed_html A list of allowed HTML tags.
	 *
	 * @return void
	 */
	private function render_links_td_section( $form, $allowed_svg_tags, $combined_allowed_html ) {
		$edit_form_link = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form['id'] );
		?>
			<td>
				<!--Edit Form-->
				<a href="<?php echo esc_url( $edit_form_link ); ?>">Edit</a> |
				<!--Duplicate Form-->
				<a href="#" onclick="DuplicateForm(<?php echo esc_attr( $form['id'] ); ?>);return false;">Duplicate</a> | <!--FIXME: Duplicate Form-->
				<!--Entries + Dropdown-->
				<?php $this->render_entries_dropdown( $form ); ?>
				<!--Settings + Dropdown-->
				<?php $this->render_settings_dropdown( $form, $allowed_svg_tags, $combined_allowed_html ); ?>
				<!--Delete Form-->
				<a href="#" class="trash" onclick="DeleteForm(<?php echo esc_attr( $form['id'] ); ?>);return false;">Trash</a>
			</td>
		<?php
	}

	/**
	 * Renders the main "Form Folders" page.
	 *
	 * @return void
	 */
	private function render_form_folders_page() {
		?>
			<div class="wrap">
				<h1>Form Folders</h1>
				<br>
				<ul>
					<?php

					$create_folder_nonce = wp_create_nonce( 'create_folder' );
					$assign_form_nonce   = wp_create_nonce( 'assign_form' );
					$view_folder_nonce   = wp_create_nonce( 'view_folder' );
					$folders             = get_terms(
						[
							'taxonomy'   => 'gf_form_folders',
							'hide_empty' => false,
						]
					);

					foreach ( $folders as $folder ) {
						$form_count = count( get_objects_in_term( $folder->term_id, 'gf_form_folders' ) );
						echo '<li style="font-size: 3em;">
				<a href="' . esc_url( admin_url( 'admin.php?page=gf-form-folders&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce ) ) . '">
				<span class="dashicons dashicons-category" style="margin-right: 5px;"></span> ' . esc_html( $folder->name ) . ' (' . esc_html( $form_count ) . ')
				</a>';
						if ( ! $form_count ) {
							$delete_folder_nonce = wp_create_nonce( 'delete_folder' );
							echo '&nbsp;&nbsp;<button class="button" onclick="delete_folder(' . esc_attr( $folder->term_id ) . ', \'' . esc_attr( $delete_folder_nonce ) . '\')">Delete Folder</button>';
						}
						echo '</li>';
						echo '<br><br>';
					}
					?>
				</ul>

				<div style="display: flex; gap: 20px; align-items: flex-start; justify-content: flex-start; max-width: 800px;">
					<div style="flex: 1;">
						<h2>Create a New Folder</h2>
						<form id="create-folder-form">
							<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $create_folder_nonce ); ?>">
							<button type="submit">Create Folder</button>
						</form>
					</div>

					<div style="flex: 1;">
						<h2>Assign a Form to a Folder</h2>
						<form id="assign-form-form">
							<select id="form_id" name="form_id" required>
								<option value="">Select an Unassigned Form</option>
								<?php
								$all_forms = GFAPI::get_forms();
								foreach ( $all_forms as $form ) {
									$assigned_folders = wp_get_object_terms( $form['id'], 'gf_form_folders', [ 'fields' => 'ids' ] );
									if ( empty( $assigned_folders ) ) {
										echo '<option value="' . esc_attr( $form['id'] ) . '">' . esc_html( $form['title'] ) . '</option>';
									}
								}
								?>
							</select>
							<select id="folder_id" name="folder_id" required>
								<option value="">Select a Folder</option>
								<?php
								foreach ( $folders as $folder ) {
									echo '<option value="' . esc_attr( $folder->term_id ) . '">' . esc_html( $folder->name ) . '</option>';
								}
								?>
							</select>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_form_nonce ); ?>">
							<button type="submit">Assign Form</button>
						</form>
					</div>
				</div>
				<script>
					document.addEventListener('DOMContentLoaded', function() {
						delete_folder = function(folder_id, nonce) {
							const body = `action=delete_folder&folder_id=${encodeURIComponent(folder_id)}&nonce=${encodeURIComponent(nonce)}`;
							fetch(ajaxurl, {
									method: 'POST',
									headers: {
										'Content-Type': 'application/x-www-form-urlencoded', // Specify the correct content type
									},
									body,
								})
								.then(response => response.json())
								.then(() => location.reload())
								.catch(error => console.error('Error:', error));
						}

						function handleFormSubmission(formId, action) {
							document.getElementById(formId).addEventListener('submit', function(e) {
								e.preventDefault();

								let formData = new FormData(this);
								formData.append('action', action);

								fetch(ajaxurl, {
										method: 'POST',
										body: formData
									})
									.then(response => response.json())
									.then(() => location.reload());
							});
						}

						handleFormSubmission('create-folder-form', 'create_folder');
						handleFormSubmission('assign-form-form', 'assign_form_to_folder');
					});
				</script>
			</div>
		<?php
	}
	/**
	 * Renders the "Entries" dropdown for a specific form in the folder.
	 *
	 * @param array $form The current form.
	 *
	 * @return void
	 */
	private function render_entries_dropdown( $form ) {
		$entries_link        = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $form['id'] );
		$export_entries_link = admin_url( 'admin.php?page=gf_export&view=export_entry&id=' . $form['id'] );
		if ( in_array( 'gravityview-importer/gravityview-importer.php', get_option( 'active_plugins' ), true ) ) {
			$import_entries_link = admin_url( 'admin.php?page=gv-admin-import-entries#targetForm=' . $form['id'] );
		}
		?>
			<div class="dropdown">
				<a href="<?php echo esc_url( $entries_link ); ?>" class="link">Entries</a>
				<ul class="dropdown-menu">
					<li>
						<a href="<?php echo esc_url( $entries_link ); ?>">Entries</a>
					</li>
					<li>
						<a href="<?php echo esc_url( $export_entries_link ); ?>"> Export Entries </a>
					</li>
					<?php
					if ( isset( $import_entries_link ) ) {
						?>
						<li><a href="<?php echo esc_url( $import_entries_link ); ?>">Import Entries</a></li>
						<?php
					}
					?>
				</ul>
			</div> |
		<?php
	}

    /**
     * Renders the "Settings" dropdown for a specific form in the folder.
     *
     * @param array $form The current form.
     * @param array $allowed_svg_tags The allowed SVG tags.
     * @param array $combined_allowed_html The combined allowed HTML tags.
     *
     * @return void
     */
	private function render_settings_dropdown( array $form, array $allowed_svg_tags, array $combined_allowed_html ) {
        $form_settings_link = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=settings&id=' . $form['id'] );

		$settings_info = GFForms::get_form_settings_sub_menu_items( $form['id'] );
		?>
			<div class="dropdown">
				<a href="<?php echo esc_url( $form_settings_link ); ?>" class="link">Settings</a>
				<ul class="dropdown-menu">
					<?php
					foreach ( $settings_info as $setting ) {
						$icon_html   = $setting['icon'];
						$icon_output = '';

						if ( preg_match( '/<svg.*<\/svg>/is', $icon_html, $matches ) ) {
							$icon_output = wp_kses( $matches[0], $allowed_svg_tags );
						} elseif ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $icon_html, $matches ) ) {
							// Icon is an <img> tag
							$icon_output = '<img src="' . esc_url( $matches[1] ) . '" alt="" class="settings-icon" />';
						} elseif ( preg_match( '/class=["\']([^"\']+)["\']/', $icon_html, $matches ) ) {
							// Icon is a class-based icon
							$classes = explode( ' ', $matches[1] );
							$classes = array_map( 'sanitize_html_class', $classes );
							$classes = implode( ' ', $classes );

							$icon_output = '<span class="dashicons ' . esc_attr( $classes ) . '"></span>';
						}
						?>
						<li>
							<a href="<?= esc_url( $setting['url'] ); ?>" class="settings-item">
								<?= wp_kses( $icon_output, $combined_allowed_html ); ?>
								<?= esc_html( $setting['label'] ); ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
			</div> |
		<?php
	}
}

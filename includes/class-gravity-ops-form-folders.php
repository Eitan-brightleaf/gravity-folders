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
class Gravity_Ops_Form_Folders extends GFAddOn {


	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
	/**
	 * The current version of the plugin
	 *
	 * @var string
	 */
	protected $_version = FOLDERS_4_GRAVITY_VERSION;
	/**
	 * A string representing the slug used for the plugin.
	 *
	 * @var string
	 */
	protected $_slug = 'forms-folders';
	/**
	 * The basename path of the plugin
	 *
	 * @var string
	 */
	protected $_path = FOLDERS_4_GRAVITY_BASENAME;
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
	protected $_title = 'Folders4Gravity';
	/**
	 * The short title of the plugin.
	 *
	 * @var string
	 */
	protected $_short_title = 'Folders4Gravity';
	/**
	 * Holds a list of capabilities.
	 *
	 * @var array
	 */
	protected $_capabilities = [ 'go_folders_4_gravity_uninstall' ];
	/**
	 * Holds the capability required for uninstallation.
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'go_folders_4_gravity_uninstall';
	/**
	 * Holds the singleton instance of the class.
	 *
	 * @var self|null
	 */
	private static ?self $_instance = null;
	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
     * The prefix to be used by the plugin. Gravity Ops-Folders4Gravity
     *
     * @var string
     */
	private $prefix = 'go_f4g_';

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

		add_action( "wp_ajax_{$this->prefix}create_folder", [ $this, 'handle_create_folder' ] );
		add_action( "wp_ajax_{$this->prefix}assign_forms_to_folder", [ $this, 'handle_assign_forms_to_folder' ] );
		add_action( "wp_ajax_{$this->prefix}remove_form_from_folder", [ $this, 'handle_remove_form_from_folder' ] );
		add_action( "wp_ajax_{$this->prefix}rename_folder", [ $this, 'handle_folder_renaming' ] );
		add_action( "wp_ajax_{$this->prefix}delete_folder", [ $this, 'handle_folder_deletion' ] );
        add_action( "wp_ajax_{$this->prefix}duplicate_form", [ $this, 'handle_duplicate_form' ] );
        add_action( "wp_ajax_{$this->prefix}trash_form", [ $this, 'handle_trash_form' ] );
	}

	/**
	 * Initializes the admin functionality of the plugin.
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'admin_menu', [ $this, 'register_form_folders_submenu' ], 15 );
        add_action(
            'wp_dashboard_setup',
            function () {
				wp_add_dashboard_widget(
                    'folders_4_gravity_dashboard_widget',
                    'Form Folders',
                    [ $this, 'dashboard_widget' ]
				);
			}
        );
	}

    public function dashboard_widget() {
        $folders = get_terms(
            [
				'taxonomy'   => 'gf_form_folders',
				'hide_empty' => false,
			]
        );

        $views = get_terms(
                [
                    'taxonomy'   => 'gv_view_folders',
					'hide_empty' => false,
				]
		);

        $view_folder_nonce = wp_create_nonce( 'view_folder' );

        ?>
        <div class="folder-columns">
            <div class="forms">
                <h1 class="folder-type-title">Form Folders</h1>
		        <br>
		        <ul>
			        <?php
			        foreach ( $folders as $folder ) {
                        $form_count  = count( get_objects_in_term( $folder->term_id, 'gf_form_folders' ) );
                        $folder_link = admin_url( 'admin.php?page=' . $this->_slug . '&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce );
                        ?>
                        <li class="folder-item">
                            <a href="<?php echo esc_url( $folder_link ); ?>" target="_blank">
                                <span class="dashicons dashicons-category folder-icon"></span>
                                <span class="folder-name"><?php echo esc_html( $folder->name ); ?> (<?php echo esc_html( $form_count ); ?>)</span>
                            </a>
                        </li>
	    		        <?php
    			    }
			        ?>
		        </ul>
		    </div>
		    <div class="views">
                <h1 class="folder-type-title">View Folders</h1>
                <br>
                <ul>
                    <?php
                    foreach ( $views as $view ) {
                        $views_count = count( get_objects_in_term( $view->term_id, 'gv_view_folders' ) );
                        $folder_link = admin_url( 'admin.php?page=gv-view-folders&folder_id=' . $view->term_id . '&view_folder_nonce=' . $view_folder_nonce );
                        ?>
                        <li class="folder-item">
                            <a href="<?php echo esc_url( $folder_link ); ?>" target="_blank">
                                <span class="dashicons dashicons-category folder-icon"></span>
                                <span class="folder-name"><?php echo esc_html( $view->name ); ?> (<?php echo esc_html( $views_count ); ?>)</span>
                            </a>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
            </div>
        </div>
		<?php
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
			$this->_slug,
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
		if ( ! taxonomy_exists( 'gf_form_folders' ) ) {
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

		if ( empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => 'Folder name is required' ], 403 );
			wp_die();
		}

		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );
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
	public function handle_assign_forms_to_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assign_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
			wp_die();
		}

		if ( empty( $_POST['formIDs'] ) || empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'Form and Folder are required' ] );
			wp_die();
		}

		$form_ids  = array_map( 'absint', (array) $_POST['formIDs'] );
		$folder_id = absint( $_POST['folderID'] );

		foreach ( $form_ids as $form_id ) {
            $result = wp_set_object_terms( $form_id, [ $folder_id ], 'gf_form_folders' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
				wp_die();
			}
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

		if ( empty( $_POST['formID'] ) ) {
			wp_send_json_error( [ 'message' => 'Form ID is required' ], 403 );
			wp_die();
		}

		$form_id = absint( $_POST['formID'] );

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

		if ( empty( $_POST['folderID'] ) || empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
			wp_die();
		}

		$folder_id   = absint( $_POST['folderID'] );
		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );

		$folder = get_term( $folder_id, 'gf_form_folders' );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => 'The specified folder does not exist.' ], 404 );
		}

		// Update the folder name
		$updated_folder = wp_update_term( $folder_id, 'gf_form_folders', [ 'name' => $folder_name ] );
		if ( is_wp_error( $updated_folder ) ) {
			wp_send_json_error( [ 'message' => 'Failed to rename the folder. Please try again.' ] );
		}

		wp_send_json_success( [ 'message' => 'Folder renamed successfully.' ] );
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
		if ( empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
		}
		$folder_id = absint( $_POST['folderID'] );
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
     * Deletes all plugin created data during uninstall
     *
     * @return void
     */
    public function uninstall() {

        $forms = GFAPI::get_forms();
        foreach ( $forms as $form ) {
	        wp_set_object_terms( $form['id'], [], 'gf_form_folders' );
        }

		// Delete the taxonomy folders
		$folder_ids = get_terms(
            [
				'taxonomy'   => 'gf_form_folders',
				'hide_empty' => false,
				'fields'     => 'ids',
			]
            );

		if ( ! is_wp_error( $folder_ids ) ) {
			foreach ( $folder_ids as $folder ) {
				wp_delete_term( $folder, 'gf_form_folders' );
			}
		}
    }

    /**
     * Duplicates a form via an AJAX request.

     * @return void
     */
    public function handle_duplicate_form() {

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'duplicate_form' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['formID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
        }

        $form_id   = absint( $_POST['formID'] );
        $folder_id = absint( $_POST['folderID'] ) ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $result    = GFAPI::duplicate_form( $form_id );
        if ( ! is_wp_error( $result ) ) {
            if ( $folder_id ) {
                wp_set_object_terms( $result, [ $folder_id ], 'gf_form_folders' );
            }
            wp_send_json_success( [ 'message' => 'Form duplicated successfully.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to duplicate the form. Please try again.' ], 403 );
            wp_die();
        }
    }


    /**
     * Trashes a form via an AJAX request.
     *
     * @return void
     */
    public function handle_trash_form() {

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'trash_form' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['formID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
        }

        $form_id = absint( $_POST['formID'] );
        $result  = GFFormsModel::trash_form( $form_id ); // method returns true for failure to trash and false for successfully trashing. weird.
        if ( ! is_wp_error( $result ) && ! $result ) {

            $result = wp_set_object_terms( $form_id, [], 'gf_form_folders' );

			if ( is_wp_error( $result ) ) {
				GFFormsModel::restore_form( $form_id );
                wp_send_json_error( [ 'message' => 'Encountered an error removing the form from the folder.' ], 403 );
                wp_die();

			}

            wp_send_json_success( [ 'message' => 'Form trashed successfully.' ] );

        } else {
            wp_send_json_error( [ 'message' => 'Failed to trash the form. Please try again.' ], 403 );
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
				'src'     => plugins_url( 'assets/css/folders_stylesheet.css', FOLDERS_4_GRAVITY_BASENAME ),
				'version' => '1.0.0',
				'enqueue' => [
					[ 'query' => 'page=' . $this->_slug ],
				],
			],
			[
                'handle'  => 'folders4formswidget',
                'src'     => plugins_url( 'assets/css/dashboard-widget.css', FOLDERS_4_GRAVITY_BASENAME ),
                'version' => '1.0.0',
                'enqueue' => [
					function () {
							$screen = get_current_screen();
							return is_admin() && $screen && 'dashboard' === $screen->id;
					},
				],
            ],
		];
		return array_merge( parent::styles(), $styles );
	}

    /**
     * Loads scripts for the plugin
     *
     * @return array[]
     */
    public function scripts() {
        $scripts = [
            [
                'handle'  => 'form-folders-scripts',
                'src'     => plugins_url( 'assets/js/folders_script.js', FOLDERS_4_GRAVITY_BASENAME ),
                'version' => '1.0.0',
                'deps'    => [ 'jquery' ],
                'enqueue' => [
                    [ 'query' => 'page=' . $this->_slug ],
                ],
            ],
        ];
        return array_merge( parent::scripts(), $scripts );
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

        wp_add_inline_script(
		'form-folders-scripts',
		'const ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";',
		'before'
	    );

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
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->_slug ) ); ?>" class="button">
					Back to All Folders
				</a>
				<br><br>

				<!--Forms Table-->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Form Name</th>
							<th>Shortcode</th>
							<th>Links</th>
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
                        $duplicate_form_nonce  = wp_create_nonce( 'duplicate_form' );
                        $trash_form_nonce      = wp_create_nonce( 'trash_form' );
                        $rename_folder_nonce   = wp_create_nonce( 'rename_folder' );
                        $assign_form_nonce     = wp_create_nonce( 'assign_form' );

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
									<?php $this->render_buttons_td_section( $form, $remove_form_nonce, $duplicate_form_nonce, $trash_form_nonce ); ?>
								</tr>
								<?php
							}
						}

						if ( ! $found ) {
							echo '<tr><td colspan="4">No forms found in this folder.</td></tr>';
						}
						?>
					</tbody>
				</table>
				<br><br>

				<!--Rename Folder-->
				<form id="rename-folder-form">
					<label for="folder_name" class="form-field-label">Rename Folder</label><br>
					<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $rename_folder_nonce ); ?>">
					<button type="submit" class="button">Rename Folder</button>
				</form>

                <br><br>

                <!--Assign Forms to Current Folder-->
				<form id="assign-forms-form">
					<label for="form_ids" class="form-field-label">Assign Forms to Folder</label><br>
					<select id="form_ids" name="form_ids[]" required multiple size="8">
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
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_form_nonce ); ?>"> <br>
					<button type="submit" class="button">Assign Forms</button>
				</form>

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
				<!--Entries + Dropdown-->
				<?php $this->render_entries_dropdown( $form ); ?>
				<!--Settings + Dropdown-->
				<?php $this->render_settings_dropdown( $form, $allowed_svg_tags, $combined_allowed_html ); ?>
				<!--Live Preview-->
				<?php $this->maybe_render_live_preview_link( $form ); ?>
				<!--Connected Views-->
				<?php $this->maybe_render_connected_views_link( $form ); ?>
			</td>
		<?php
	}

	/**
	 * Renders the main "Form Folders" page.
	 *
	 * @return void
	 */
	private function render_form_folders_page() {

        $create_folder_nonce = wp_create_nonce( 'create_folder' );
        $assign_form_nonce   = wp_create_nonce( 'assign_form' );
        $view_folder_nonce   = wp_create_nonce( 'view_folder' );
        $delete_folder_nonce = wp_create_nonce( 'delete_folder' );
        $folders             = get_terms(
						            [
							            'taxonomy'   => 'gf_form_folders',
							            'hide_empty' => false,
						            ]
					            );

		?>
			<div class="wrap">
				<h1>Form Folders</h1>
				<br>
				<ul>
					<?php

					foreach ( $folders as $folder ) {
						$form_count  = count( get_objects_in_term( $folder->term_id, 'gf_form_folders' ) );
                        $folder_link = admin_url( 'admin.php?page=' . $this->_slug . '&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce );
                        ?>
                        <li class="folder-item">
                            <a href="<?php echo esc_url( $folder_link ); ?>">
                                <span class="dashicons dashicons-category folder-icon"></span> <?php echo esc_html( $folder->name ); ?> (<?php echo esc_html( $form_count ); ?>)
                            </a>
                        <?php
						if ( ! $form_count ) {
                            ?>
							&nbsp;&nbsp;
							<button class="button delete-folder-button" data-folder-id="<?php echo esc_attr( $folder->term_id ); ?>" data-nonce="<?php echo esc_attr( $delete_folder_nonce ); ?>">Delete Folder</button>
							<?php
						}
                        ?>
                        </li>
					    <br><br>
						<?php
					}
					?>
				</ul>

				<div class="folder-forms">
					<div class="folder-forms-item">
						<form id="create-folder-form">
						    <label for="folder_name" class="form-field-label">Create A New Folder</label><br>
							<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $create_folder_nonce ); ?>">
							<button type="submit" class="button">Create Folder</button>
						</form>
					</div>

					<div class="folder-forms-item">
					    <label for="assign-forms-form" class="form-field-label">Assign Form(s) to a Folder</label>
						<form id="assign-forms-form">
							<label for="form_id" class="form-field-sub-label">Select Form(s) to Assign</label><br>
							<select id="form_id" name="form_ids[]" required multiple size="8">
								<?php
								$all_forms = GFAPI::get_forms();
								foreach ( $all_forms as $form ) {
									$assigned_folders = wp_get_object_terms( $form['id'], 'gf_form_folders', [ 'fields' => 'ids' ] );
									if ( empty( $assigned_folders ) ) {
                                        ?>
										<option value="<?php echo esc_attr( $form['id'] ); ?>"><?php echo esc_html( $form['title'] ); ?></option>
										<?php
									}
								}
								?>
							</select>
							<br><br>
							<label for="folder_id" class="form-field-sub-label">Select a Folder to Assign To</label><br>
							<select id="folder_id" name="folder_id" required>
								<option value="">Select a Folder</option>
								<?php
								foreach ( $folders as $folder ) {
                                    ?>
									<option value="<?php echo esc_attr( $folder->term_id ); ?>"><?php echo esc_html( $folder->name ); ?></option>
									<?php
								}
								?>
							</select>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_form_nonce ); ?>">
							<button type="submit" class="button">Assign Form(s)</button>
						</form>
					</div>
				</div>
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
	private function render_settings_dropdown( $form, $allowed_svg_tags, $combined_allowed_html ) {
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
							<a href="<?php echo esc_url( $setting['url'] ); ?>" class="settings-item">
								<?php echo wp_kses( $icon_output, $combined_allowed_html ); ?>
								<?php echo esc_html( $setting['label'] ); ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
		<?php
	}
    /**
     * Renders the "Buttons" section of the table for a specific form in the folder.

     * @param array  $form The current form.
     * @param string $remove_form_nonce The nonce for removing the form.
     * @param string $duplicate_form_nonce The nonce for duplicating the form.
     * @param string $trash_form_nonce The nonce for trashing the form.
     *
     * @return void
     */
    private function render_buttons_td_section( $form, $remove_form_nonce, $duplicate_form_nonce, $trash_form_nonce ) {
        ?>
                <td>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>remove_form_from_folder" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $remove_form_nonce ); ?>">
                        Remove
                    </button>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>duplicate_form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $duplicate_form_nonce ); ?>">
                        Duplicate
                    </button>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>trash_form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $trash_form_nonce ); ?>">
                        Trash
                    </button>
                </td>
                <?php
	}

	/**
     * Conditionally renders a live preview link for a form if the GP_Live_Preview class is available.
     *
     * @param array $form The form array containing form data including the form ID.
     *
     * @return void
     */
	private function maybe_render_live_preview_link( array $form ) {
		if ( ! class_exists( 'GP_Live_Preview' ) ) {
			return;
		}
			$gp_live_preview = GP_Live_Preview::get_instance();
			$preview_url     = $gp_live_preview->add_options_to_url( $gp_live_preview->get_preview_url( $form['id'] ) );
		?>
			| <a href="<?php echo esc_url( $preview_url ); ?>" target="_blank">Live Preview</a>
			<?php
	}
    /**
     * Creates a dropdown of all views connected to the form or a create view link
     *
     * @param array $form The current form.
     *
     * @return void
     */
	private function maybe_render_connected_views_link( array $form ) {
			// Check if GravityView is active
		if ( ! class_exists( 'GVCommon' ) ) {
			return;
		}

			// Get connected views for this form
			$connected_views = GVCommon::get_connected_views( $form['id'], [ 'post_status' => 'any' ] );

			// If no connected views, show a link to create one
		if ( empty( $connected_views ) ) {
			?>
				| <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gravityview&form_id=' . $form['id'] ) ); ?>" target="_blank">Create a View</a>
			<?php
			return;
		}

			// If there are connected views, show a dropdown
		?>
			<div class="dropdown">
				| <a href="#" class="link">Connected Views</a>
				<ul class="dropdown-menu">
					<?php
					foreach ( $connected_views as $view ) {
						$label = empty( $view->post_title ) ? sprintf( 'No Title (View #%d)', $view->ID ) : $view->post_title;
						?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $view->ID ) ); ?>">
								<?php echo esc_html( $label ); ?>
							</a>
						</li>
						<?php
					}
					?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gravityview&form_id=' . $form['id'] ) ); ?>">
							<span class="dashicons dashicons-plus"></span>
							Create a View
						</a>
					</li>
				</ul>
			</div>
		<?php
	}
}

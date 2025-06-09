<?php

use GV\View;if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

GFForms::include_addon_framework();

/**
 * Class Views_Folders
 *
 * This class extends the GFAddOn and is responsible for handling the Views Folders for GravityView plugin.
 * It duplicates the functionality of Form_Folders but for GravityView views.
 */
class Gravity_Ops_Views_Folders extends GFAddOn {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
	/**
	 * The current version of the plugin
	 *
	 * @var string
	 */
	protected $_version = GRAVITY_FOLDERS_VERSION;
	/**
	 * A string representing the slug used for the plugin.
	 *
	 * @var string
	 */
	protected $_slug = 'views-folders';
	/**
	 * The basename path of the plugin
	 *
	 * @var string
	 */
	protected $_path = GRAVITY_FOLDERS_BASENAME;
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
	protected $_title = 'View Folders for GravityView';
	/**
	 * The short title of the plugin.
	 *
	 * @var string
	 */
	protected $_short_title = 'View Folders';
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
		$this->register_views_folders_taxonomy();

		add_action( 'wp_ajax_create_view_folder', [ $this, 'handle_create_folder' ] );
		add_action( 'wp_ajax_assign_views_to_folder', [ $this, 'handle_assign_views_to_folder' ] );
		add_action( 'wp_ajax_remove_view_from_folder', [ $this, 'handle_remove_view_from_folder' ] );
		add_action( 'wp_ajax_rename_view_folder', [ $this, 'handle_folder_renaming' ] );
		add_action( 'wp_ajax_delete_view_folder', [ $this, 'handle_folder_deletion' ] );
		add_action( 'wp_ajax_clone_view', [ $this, 'handle_clone_view' ] );
		add_action( 'wp_ajax_trash_view', [ $this, 'handle_trash_view' ] );
	}

	/**
	 * Initializes the admin functionality of the plugin.
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'admin_menu', [ $this, 'register_views_folders_submenu' ], 1000 );
	}

	/**
	 * Registers a submenu page under the GravityKit menu for view folders.
	 * Adds it to the bottom of the menu below a divider line.
	 *
	 * @return void
	 */
	public function register_views_folders_submenu() {
		global $submenu;

		// First, add the submenu page
		add_submenu_page(
			'_gk_admin_menu',
			'View Folders',
			'View Folders',
			'edit_gravityviews',
			'gv-view-folders',
			[ $this, 'view_folders_page' ]
		);

		// Then, add a divider before our menu item
		if ( isset( $submenu['_gk_admin_menu'] ) ) {
			// Find the position of our menu item
			$position = null;
			foreach ( $submenu['_gk_admin_menu'] as $key => $item ) {
				if ( 'gv-view-folders' === $item[2] ) {
					$position = $key;
					break;
				}
			}

			// If found, add a divider before it
			if ( ! is_null( $position ) && $position > 0 ) {
				$previous_item                              = $submenu['_gk_admin_menu'][ $position - 1 ];
				$previous_item[0]                          .= '</a><hr style="margin: 10px 12px; border: none; height: 1px; background-color: hsla( 0, 0%, 100%, .2 );" tabindex="-1" />';
				$submenu['_gk_admin_menu'][ $position - 1 ] = $previous_item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Registers a custom taxonomy for organizing views into folders.
	 *
	 * The taxonomy 'gv_view_folders' is associated with the 'gravityview' post type. It is not publicly queryable,
	 * does not have URL rewrites, and supports a non-hierarchical structure. It includes an admin column for easier management in the admin interface.
	 *
	 * @return void
	 */
	private function register_views_folders_taxonomy() {
		if ( ! taxonomy_exists( 'gv_view_folders' ) ) {
			register_taxonomy(
			'gv_view_folders',
			'gravityview',
			[
				'label'             => 'View Folders',
				'rewrite'           => false,
				'public'            => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
			]
			);
        }
	}

	/**
	 * Handles the creation of a new folder for views.
	 *
	 * Validates the current user's permission and the provided folder name.
	 * Inserts a new term into the 'gv_view_folders' taxonomy. Returns a success or error message depending on the outcome.
	 *
	 * @return void Sends a JSON response indicating success or failure.
	 */
	public function handle_create_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'create_view_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'edit_gravityviews' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => 'Folder name is required' ], 403 );
			wp_die();
		}

		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );
		$inserted    = wp_insert_term( $folder_name, 'gv_view_folders' );

		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( [ 'message' => $inserted->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Folder created successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the process of assigning a view to a folder.
	 *
	 * Ensures the current user has the necessary permissions to perform the action.
	 * Validates required input data, assigns the view to the specified folder,
	 * and returns the appropriate success or error messages.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 */
	public function handle_assign_views_to_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assign_view' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'edit_gravityviews' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
			wp_die();
		}

		if ( empty( $_POST['viewIDs'] ) || empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'View and Folder are required' ] );
			wp_die();
		}

		$view_ids  = array_map( 'absint', (array) $_POST['viewIDs'] );
		$folder_id = absint( $_POST['folderID'] );

		foreach ( $view_ids as $view_id ) {
            $result = wp_set_object_terms( $view_id, [ $folder_id ], 'gv_view_folders' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
				wp_die();
			}
		}

		wp_send_json_success( [ 'message' => 'View assigned successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the removal of a view from a folder.
	 *
	 * This function validates the nonce, checks user permissions, and removes the specified view
	 * from its associated folder. It sends a JSON response indicating success or failure.
	 *
	 * @return void Outputs a JSON response and terminates execution.
	 */
	public function handle_remove_view_from_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'remove_view' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'edit_gravityviews' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['viewID'] ) ) {
			wp_send_json_error( [ 'message' => 'View ID is required' ], 403 );
			wp_die();
		}

		$view_id = absint( $_POST['viewID'] );

		$result = wp_set_object_terms( $view_id, [], 'gv_view_folders' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'View removed from the folder successfully!' ] );
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rename_view_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folderID'] ) || empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'my-textdomain' ) ], 400 );
			wp_die();
		}

		$folder_id   = absint( $_POST['folderID'] );
		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );

		$folder = get_term( $folder_id, 'gv_view_folders' );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => __( 'The specified folder does not exist.', 'my-textdomain' ) ], 404 );
		}

		// Update the folder name
		$updated_folder = wp_update_term( $folder_id, 'gv_view_folders', [ 'name' => $folder_name ] );
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_view_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}
		if ( empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
		}
		$folder_id = absint( $_POST['folderID'] );
		$folder    = get_term( $folder_id, 'gv_view_folders' );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => 'The specified folder does not exist.' ], 404 );
		}
		$result = wp_delete_term( $folder_id, 'gv_view_folders' );
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
        // Get all views
        $views = get_posts(
            [
				'post_type'   => 'gravityview',
				'numberposts' => -1,
				'post_status' => 'any',
			]
            );

        // Remove all views from folders
        foreach ( $views as $view ) {
	        wp_set_object_terms( $view->ID, [], 'gv_view_folders' );
        }

		// Delete the taxonomy folders
		$folder_ids = get_terms(
            [
				'taxonomy'   => 'gv_view_folders',
				'hide_empty' => false,
				'fields'     => 'ids',
			]
            );

		if ( ! is_wp_error( $folder_ids ) ) {
			foreach ( $folder_ids as $folder ) {
				wp_delete_term( $folder, 'gv_view_folders' );
			}
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
				'handle'  => 'view-folders-styles',
				'src'     => plugins_url( 'assets/css/folders_stylesheet.css', GRAVITY_FOLDERS_BASENAME ),
				'version' => '1.0.0',
				'enqueue' => [
					[ 'query' => 'page=gv-view-folders' ],
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
                'handle'  => 'view-folders-scripts',
                'src'     => plugins_url( 'assets/js/views_folders_script.js', GRAVITY_FOLDERS_BASENAME ),
                'version' => '1.0.0',
                'deps'    => [ 'jquery' ],
                'enqueue' => [
                    [ 'query' => 'page=gv-view-folders' ],
                ],
            ],
        ];
        return array_merge( parent::scripts(), $scripts );
    }

	/**
	 * Renders the View Folders admin page.
	 *
	 * This method displays the main "View Folders" page or a detailed view of a specific folder
	 * with its assigned views. Includes functionality for viewing views within a folder, creating
	 * new folders, and assigning views to folders. Access is restricted to users with edit_gravityviews capability.
	 *
	 * @return void
	 */
	public function view_folders_page() {
		if ( ! current_user_can( 'edit_gravityviews' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

        wp_add_inline_script(
		'view-folders-scripts',
		'const ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";',
		'before'
	    );

		if ( rgget( 'folder_id' ) ) {
			$this->render_single_folder_page();
		} else {
			$this->render_view_folders_page();
		}
	}

	/**
	 * Renders a single folder page with its assigned views.
	 *
	 * @return void
	 */
	private function render_single_folder_page() {
		if ( ! isset( $_GET['view_folder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['view_folder_nonce'] ) ), 'view_folder' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}
		$folder_id = isset( $_GET['folder_id'] ) ? absint( $_GET['folder_id'] ) : 0;

		if ( $folder_id ) {
			$folder = get_term( $folder_id, 'gv_view_folders' );
			if ( is_wp_error( $folder ) || ! $folder ) {
				echo '<div class="error"><p>Invalid folder.</p></div>';
				return;
			}
			?>

			<div class="wrap">
				<h1>Views in Folder: <?php echo esc_html( $folder->name ); ?> </h1>
				<!--Back button-->
				<br>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-view-folders' ) ); ?>" class="button">
					Back to All Folders
				</a>
				<br><br>

				<!--Views Table-->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>View Name</th>
							<th>Shortcode</th>
							<th>Links</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>

						<?php
						$views               = get_posts(
                            [
								'post_type'   => 'gravityview',
								'numberposts' => -1,
								'post_status' => 'any',
							]
                            );
						$found               = false;
						$remove_view_nonce   = wp_create_nonce( 'remove_view' );
                        $rename_folder_nonce = wp_create_nonce( 'rename_view_folder' );
                        $assign_view_nonce   = wp_create_nonce( 'assign_view' );

						foreach ( $views as $view ) {
							$view_terms     = wp_get_object_terms( $view->ID, 'gv_view_folders', [ 'fields' => 'ids' ] );
							$edit_view_link = admin_url( 'post.php?action=edit&post=' . $view->ID );
                            $form_id        = get_post_meta( $view->ID, '_gravityview_form_id', true );
                            $form           = GFAPI::get_form( $form_id );
                            $form_title     = $form ? $form['title'] : 'Unknown Form';

							if ( in_array( $folder_id, $view_terms, true ) ) {
								$found = true;
								?>
								<tr>
									<!--View Title-->
									<td>
										<a href="<?php echo esc_url( $edit_view_link ); ?>"><?php echo esc_html( $view->post_title ); ?></a>
									</td>
									<!--Shortcode-->
									<td>
										<code class="copyable">
											<?php
											// Check if GVCommon class exists and get the secret if available
											if ( class_exists( 'GVCommon' ) ) {
												$view_obj = View::by_id( $view->ID );
												$secret   = $view_obj?->get_validation_secret();
												if ( $secret ) {
													echo '[gravityview id="' . esc_attr( $view->ID ) . '" secret="' . esc_attr( $secret ) . '"]';
												} else {
													echo '[gravityview id="' . esc_attr( $view->ID ) . '"]';
												}
											} else {
												echo '[gravityview id="' . esc_attr( $view->ID ) . '"]';
											}
											?>
										</code>
									</td>
									<!--Links-->
									<td>
									    <a href="<?php echo esc_url( $edit_view_link ); ?>">Edit</a> |
                                        <a href="<?php echo esc_url( get_permalink( $view->ID ) ); ?>">View</a> |
                                        <?php if ( $form ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id ) ); ?>"><?php echo esc_html( $form_title ); ?></a> |
                                        <?php else : ?>
                                            <?php echo esc_html( $form_title ); ?> |
                                        <?php endif; ?>
                                    </td>
									<!--Actions-->
									<td>
                                        <button type="button" class="update-view button" data-action="remove_view_from_folder" data-view-id="<?php echo esc_attr( $view->ID ); ?>" data-nonce="<?php echo esc_attr( $remove_view_nonce ); ?>">
                                            Remove
                                        </button>
                                        <?php
                                        // Add clone button
                                        $clone_view_nonce = wp_create_nonce( 'clone_view' );
                                        ?>
                                        <button type="button" class="update-view button" data-action="clone_view" data-view-id="<?php echo esc_attr( $view->ID ); ?>" data-nonce="<?php echo esc_attr( $clone_view_nonce ); ?>">
                                            Clone
                                        </button>
                                        <?php
                                        // Add trash button
                                        $trash_view_nonce = wp_create_nonce( 'trash_view' );
                                        ?>
                                        <button type="button" class="update-view button" data-action="trash_view" data-view-id="<?php echo esc_attr( $view->ID ); ?>" data-nonce="<?php echo esc_attr( $trash_view_nonce ); ?>">
                                            Trash
                                        </button>
                                    </td>
								</tr>
								<?php
							}
						}

						if ( ! $found ) {
							echo '<tr><td colspan="4">No views found in this folder.</td></tr>';
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

                <!--Assign Views to Current Folder-->
				<form id="assign-views-form">
					<label for="view_ids" class="form-field-label">Assign Views to Folder</label><br>
					<select id="view_ids" name="view_ids[]" required multiple size="8">
						<?php
						$all_views = get_posts(
                            [
								'post_type'   => 'gravityview',
								'numberposts' => -1,
								'post_status' => 'any',
							]
                            );
						foreach ( $all_views as $view ) {
							$assigned_folders = wp_get_object_terms( $view->ID, 'gv_view_folders', [ 'fields' => 'ids' ] );
							if ( empty( $assigned_folders ) ) {
								echo '<option value="' . esc_attr( $view->ID ) . '">' . esc_html( $view->post_title ) . '</option>';
							}
						}
						?>
					</select>
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_view_nonce ); ?>"> <br>
					<button type="submit" class="button">Assign Views</button>
				</form>

			<?php
			echo '</div>';
		}
	}

    /**
     * Handles cloning a view via an AJAX request.
     *
     * @return void
     */
    public function handle_clone_view() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'clone_view' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( ! current_user_can( 'edit_gravityviews' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['viewID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
            wp_die();
        }

        $view_id   = absint( $_POST['viewID'] );
        $folder_id = isset( $_POST['folderID'] ) ? absint( $_POST['folderID'] ) : null;

        // Get the original view
        $original_view = get_post( $view_id );

        if ( ! $original_view || 'gravityview' !== $original_view->post_type ) {
            wp_send_json_error( [ 'message' => 'Invalid view ID.' ], 400 );
            wp_die();
        }

        // Create a duplicate
        $new_view_id = $this->duplicate_view( $view_id );

        if ( is_wp_error( $new_view_id ) ) {
            wp_send_json_error( [ 'message' => $new_view_id->get_error_message() ], 400 );
            wp_die();
        }

        // If we have a folder ID, assign the new view to that folder
        if ( $folder_id ) {
            wp_set_object_terms( $new_view_id, [ $folder_id ], 'gv_view_folders' );
        }

        wp_send_json_success( [ 'message' => 'View cloned successfully.' ] );
        wp_die();
    }

    /**
     * Duplicates a GravityView view
     *
     * @param int $view_id The ID of the view to duplicate.
     * @return int|WP_Error The new view ID or WP_Error on failure
     */
    private function duplicate_view( $view_id ) {
        $original_view = get_post( $view_id );

        if ( ! $original_view || 'gravityview' !== $original_view->post_type ) {
            return new WP_Error( 'invalid_view', 'Invalid view ID.' );
        }

        // Create a duplicate post
        $new_view = array(
            'post_title'     => $original_view->post_title . ' (Copy)',
            'post_content'   => $original_view->post_content,
            'post_status'    => 'draft',
            'post_type'      => 'gravityview',
            'comment_status' => $original_view->comment_status,
            'ping_status'    => $original_view->ping_status,
            'post_author'    => get_current_user_id(),
        );

        // Insert the new view
        $new_view_id = wp_insert_post( $new_view );

        if ( is_wp_error( $new_view_id ) ) {
            return $new_view_id;
        }

        // Copy all the meta data
        $meta_keys = get_post_custom_keys( $view_id );

        if ( $meta_keys ) {
            foreach ( $meta_keys as $meta_key ) {
                $meta_values = get_post_custom_values( $meta_key, $view_id );

                foreach ( $meta_values as $meta_value ) {
                    $meta_value = maybe_unserialize( $meta_value );
                    add_post_meta( $new_view_id, $meta_key, $meta_value );
                }
            }
        }

        return $new_view_id;
    }

    /**
     * Handles trashing a view via an AJAX request.
     *
     * @return void
     */
    public function handle_trash_view() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'trash_view' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( ! current_user_can( 'edit_gravityviews' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['viewID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
            wp_die();
        }

        $view_id = absint( $_POST['viewID'] );

        // Remove from folder first
        wp_set_object_terms( $view_id, [], 'gv_view_folders' );

        // Trash the view
        $result = wp_trash_post( $view_id );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => 'Failed to trash the view. Please try again.' ], 400 );
            wp_die();
        }

        wp_send_json_success( [ 'message' => 'View trashed successfully.' ] );
        wp_die();
    }

	/**
	 * Renders the main "View Folders" page.
	 *
	 * @return void
	 */
	private function render_view_folders_page() {
        $create_folder_nonce = wp_create_nonce( 'create_view_folder' );
        $assign_view_nonce   = wp_create_nonce( 'assign_view' );
        $view_folder_nonce   = wp_create_nonce( 'view_folder' );
        $delete_folder_nonce = wp_create_nonce( 'delete_view_folder' );
        $folders             = get_terms(
						[
							'taxonomy'   => 'gv_view_folders',
							'hide_empty' => false,
						]
					);
		?>
			<div class="wrap">
				<h1>View Folders</h1>
				<br>
				<ul>
					<?php
					foreach ( $folders as $folder ) {
						$view_count  = count( get_objects_in_term( $folder->term_id, 'gv_view_folders' ) );
                        $folder_link = admin_url( 'admin.php?page=gv-view-folders&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce );
                        ?>
                        <li class="folder-item">
                            <a href="<?= esc_url( $folder_link ); ?>">
                                <span class="dashicons dashicons-category folder-icon"></span> <?= esc_html( $folder->name ); ?> (<?= esc_html( $view_count ); ?>)
                            </a>
                        <?php
						if ( ! $view_count ) {
                            ?>
							&nbsp;&nbsp;
							<button class="button delete-folder-button" data-folder-id="<?= esc_attr( $folder->term_id ); ?>" data-nonce="<?= esc_attr( $delete_folder_nonce ); ?>">Delete Folder</button>
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
					    <label for="assign-views-form" class="form-field-label">Assign View(s) to a Folder</label>
						<form id="assign-views-form">
							<label for="view_id" class="form-field-sub-label">Select View(s) to Assign</label><br>
							<select id="view_id" name="view_ids[]" required multiple size="8">
								<?php
								$all_views = get_posts(
                                    [
										'post_type'   => 'gravityview',
										'numberposts' => -1,
										'post_status' => 'any',
									]
                                    );
								foreach ( $all_views as $view ) {
									$assigned_folders = wp_get_object_terms( $view->ID, 'gv_view_folders', [ 'fields' => 'ids' ] );
									if ( empty( $assigned_folders ) ) {
                                        ?>
										<option value="<?= esc_attr( $view->ID ); ?>"><?= esc_html( $view->post_title ); ?></option>
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
									<option value="<?= esc_attr( $folder->term_id ); ?>"><?= esc_html( $folder->name ); ?></option>
									<?php
								}
								?>
							</select>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_view_nonce ); ?>">
							<button type="submit" class="button">Assign View(s)</button>
						</form>
					</div>
				</div>
			</div>
		<?php
	}
}

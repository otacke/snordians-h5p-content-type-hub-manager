<?php
/**
 * Main plugin class file.
 *
 * @package sustainum-h5p-content-type-hub-manager
 */

namespace Sustainum\H5PContentTypeHubManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Main plugin class.
 */
class Main {

	/**
	 * Path to the H5P classes file in the H5P plugin.
	 *
	 * @var string
	 */
	private static $h5p_classes_file_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'h5p' . DIRECTORY_SEPARATOR . 'h5p-php-library' . DIRECTORY_SEPARATOR . 'h5p.classes.php';

	/**
	 * Constructor.
	 */
	public function __construct() {
		new Options( self::get_endpoint_in_h5p_core() );
		add_action( 'update_option', array( self::class, 'handle_option_updated' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'handle_content_type_upgraded' ), 10, 2 );
		add_action( 'h5p_content_hub_manager_update_libraries', array( $this, 'update_installed_h5p_libraries' ), 10, 0 );

		$update_schedule = Options::get_update_schedule();
		if ( 'daily' === $update_schedule || 'weekly' === $update_schedule ) {
			if ( ! wp_next_scheduled( 'h5p_content_hub_manager_update_libraries' ) ) {
				wp_schedule_event( time(), $update_schedule, 'h5p_content_hub_manager_update_libraries' );
			}
		} else {
			$timestamp = wp_next_scheduled( 'h5p_content_hub_manager_update_libraries' );
			wp_unschedule_event( $timestamp, 'h5p_content_hub_manager_update_libraries' );
		}
	}

	/**
	 * Update installed H5P libraries by checking for new versions in the content type hub.
	 */
	public function update_installed_h5p_libraries() {
		$content_type_hub_connector = new ContentTypeHubConnector();
		$content_type_hub_connector->install_new_content_type_versions();
	}

	/**
	 * Handle changes to the endpoint URL base option that other plugins might use.
	 *
	 * @param string $option_name The name of the option that was updated.
	 * @param mixed  $old_value The old value of the option.
	 * @param mixed  $new_value The new value of the option.
	 */
	public static function handle_option_updated( $option_name, $old_value, $new_value ) {
		if ( Options::get_slug() !== $option_name ) {
			return;
		}

		if (
			! is_array( $new_value ) || ! isset( $new_value['endpoint_url_base'] ) ||
			! is_array( $old_value ) || ! isset( $old_value['endpoint_url_base'] )
		) {
			return;
		}

		if ( $new_value['endpoint_url_base'] === $old_value['endpoint_url_base'] ) {
			return;
		}

		self::update_endpoint_in_h5p_core( $new_value['endpoint_url_base'] );
	}

	/**
	 * Handle H5P content type upgrade to overwrite the endpoint URL in H5P core.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader instance.
	 * @param array        $hook_extra Extra information about the upgrade.
	 */
	public static function handle_content_type_upgraded( $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
			return;
		}

		if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		if ( 'h5p' !== $hook_extra['plugins'][0] ) {
			return;
		}

		self::update_endpoint_in_h5p_core( Options::get_endpoint_url_base() );
	}

	/**
	 * Get the currently set endpoint URL from H5P core.
	 *
	 * @return string The endpoint URL base.
	 */
	public static function get_endpoint_in_h5p_core() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return array();
		}

		if ( ! $wp_filesystem->exists( self::$h5p_classes_file_path ) ) {
			return array();
		}

		$file_content = $wp_filesystem->get_contents( self::$h5p_classes_file_path );

		if ( false === $file_content ) {
			return array();
		}

		preg_match_all(
			"/CONTENT_TYPES = '(.+?)\/content-types\/';/",
			$file_content,
			$matches
		);

		return $matches[1];
	}

	/**
	 * Update the endpoint URL in H5P core. Will patch the H5P classes file.
	 *
	 * @param string $endpoint_url_base The new endpoint URL base.
	 */
	public static function update_endpoint_in_h5p_core( $endpoint_url_base ) {
		global $wp_filesystem;

		if ( ! current_user_can( 'manage_h5p_content_type_hub' ) ) {
			return;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return;
		}

		if ( ! $wp_filesystem->exists( self::$h5p_classes_file_path ) ) {
			return;
		}

		$file_content = $wp_filesystem->get_contents( self::$h5p_classes_file_path );

		if ( false === $file_content ) {
			return;
		}

		$file_content = preg_replace(
			"/(CONTENT_TYPES = ')(.*)(\/content-types\/';)/",
			'$1' . $endpoint_url_base . '$3',
			$file_content
		);

		$wp_filesystem->put_contents( self::$h5p_classes_file_path, $file_content, FS_CHMOD_FILE );
	}
}

<?php
/**
 * Options page for the plugin.
 *
 * @package sustainum-h5p-content-type-hub-manager
 */

namespace Sustainum\H5PContentTypeHubManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Options page for the plugin.
 *
 * @package sustainum-h5p-content-type-hub-manager
 */
class Options {

	/**
	 * Default endpoint URL base pointing to the official H5P Content Type Hub.
	 *
	 * @var string
	 */
	const DEFAULT_ENDPOINT_URL_BASE = 'api.h5p.org/v1';

	/**
	 * Default schedule for automated updates.
	 *
	 * @var string
	 */
	const DEFAULT_UPDATE_SCHEDULE = 'never';

	/**
	 * Option slug.
	 *
	 * @var string
	 */
	private static $option_slug = 'sustainumh5pcontenttypehubmanager_option';

	/**
	 * Current endpoint URL base.
	 *
	 * @var string
	 */
	private static $current_endpoint_url_base;

	/**
	 * Options.
	 *
	 * @var array
	 */
	private static $options;

	/**
	 * Start up
	 *
	 * @param string $current_endpoint_url_base The current endpoint URL base.
	 */
	public function __construct( $current_endpoint_url_base = self::DEFAULT_ENDPOINT_URL_BASE ) {
		self::$current_endpoint_url_base = $current_endpoint_url_base;
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Get the option slug.
	 *
	 * @return string Option slug.
	 */
	public static function get_slug() {
		return self::$option_slug;
	}

	/**
	 * Set defaults.
	 *
	 * @since 0.1.0
	 */
	public static function set_defaults() {
		if ( get_option( 'sustainumh5pcontenttypehubmanager_defaults_set' ) ) {
			return; // No need to set defaults.
		}

		update_option( 'sustainumh5pcontenttypehubmanager_defaults_set', true );

		update_option(
			self::$option_slug,
			array(
				'endpoint_url_base' => self::$current_endpoint_url_base,
				'update_schedule'   => self::DEFAULT_UPDATE_SCHEDULE,
			)
		);
	}

	/**
	 * Delete options.
	 */
	public static function delete_options() {
		delete_option( self::$option_slug );
		delete_site_option( self::$option_slug );
		delete_option( 'sustainumh5pcontenttypehubmanager_defaults_set' );
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_page() {
		// This page will be under "Settings".
		add_options_page(
			'Settings Admin',
			'H5P Content Type Hub Manager',
			'manage_options',
			'sustainumh5pcontenttypehubmanager-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<h2><?php echo esc_html( __( 'H5P Content Type Hub Manager', 'sustainum-h5p-content-type-hub-manager' ) ); ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sustainumh5pcontenttypehubmanager_option_group' );
				do_settings_sections( 'sustainumh5pcontenttypehubmanager-admin' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings.
	 */
	public function page_init() {
		// The `sanitize` function properly sanitizes all input.
		// phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
		register_setting(
			'sustainumh5pcontenttypehubmanager_option_group',
			'sustainumh5pcontenttypehubmanager_option',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'general_settings',
			__( 'General', 'sustainum-h5p-content-type-hub-manager' ),
			array( $this, 'print_general_section_info' ),
			'sustainumh5pcontenttypehubmanager-admin'
		);

		add_settings_field(
			'url',
			__( 'URL', 'sustainum-h5p-content-type-hub-manager' ),
			array( $this, 'endpoint_url_base_callback' ),
			'sustainumh5pcontenttypehubmanager-admin',
			'general_settings'
		);

		add_settings_field(
			'update_schedule',
			__( 'Schedule automated updates', 'sustainum-h5p-content-type-hub-manager' ),
			array( $this, 'update_schedule_callback' ),
			'sustainumh5pcontenttypehubmanager-admin',
			'general_settings'
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @since 0.1.0
	 * @param array $input Contains all settings fields as array keys.
	 * @return array Output.
	 */
	public function sanitize( $input ) {
		$input = (array) $input;

		$new_input = array();

		$new_input['endpoint_url_base'] = empty( $input['endpoint_url_base'] ) ?
			self::DEFAULT_ENDPOINT_URL_BASE :
			sanitize_text_field( $input['endpoint_url_base'] );

		// Ensure the URL does not end with a slash.
		$new_input['endpoint_url_base'] = rtrim( $new_input['endpoint_url_base'], '/' );

		$valid_schedules              = array( 'never', 'daily', 'weekly' );
		$new_input['update_schedule'] = in_array( $input['update_schedule'], $valid_schedules, true ) ?
			$input['update_schedule'] :
			self::DEFAULT_UPDATE_SCHEDULE;

		return $new_input;
	}

	/**
	 * Print section text for general settings.
	 */
	public function print_general_section_info() {
	}

	/**
	 * Get url option.
	 */
	public function endpoint_url_base_callback() {
		// I don't like this mixing of HTML and PHP, but it seems to be WordPress custom.
		?>
		<input
			name="sustainumh5pcontenttypehubmanager_option[endpoint_url_base]"
			type="text"
			id="url"
			minlength="1"
			placeholder="<?php echo esc_attr( self::DEFAULT_ENDPOINT_URL_BASE ); ?>"
			value="<?php echo esc_attr( self::get_endpoint_url_base() ); ?>"
		/>
		<p id="output-url" class="description">
			<?php
				echo esc_html(
					sprintf(
					// Translators: %s is the default endpoint URL base of H5P Group's Content Type Hub.
						__( 'Set the desired base URL for the H5P Content Type Hub Manager. Default is %s', 'sustainum-h5p-content-type-hub-manager' ),
						esc_html( self::DEFAULT_ENDPOINT_URL_BASE )
					)
				);
			?>
		</p>
		<?php
	}

	/**
	 * Get update schedule option callback.
	 */
	public function update_schedule_callback() {
		$current_schedule = self::get_update_schedule();
		$schedules        = array(
			'never'  => __( 'Never', 'sustainum-h5p-content-type-hub-manager' ),
			'daily'  => __( 'Daily', 'sustainum-h5p-content-type-hub-manager' ),
			'weekly' => __( 'Weekly', 'sustainum-h5p-content-type-hub-manager' ),
		);
		?>
		<select name="sustainumh5pcontenttypehubmanager_option[update_schedule]" id="update_schedule">
			<?php foreach ( $schedules as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_schedule, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php echo esc_html( __( 'Choose how often to automatically update H5P content types that are already installed from the Hub.', 'sustainum-h5p-content-type-hub-manager' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Get endpoint URL base.
	 *
	 * @return string Endpoint URL base.
	 */
	public static function get_endpoint_url_base() {
		return ( isset( self::$options['endpoint_url_base'] ) ) ?
			self::$options['endpoint_url_base'] :
			self::DEFAULT_ENDPOINT_URL_BASE;
	}

	/**
	 * Get update schedule.
	 *
	 * @return string Update schedule.
	 */
	public static function get_update_schedule() {
		return ( isset( self::$options['update_schedule'] ) ) ?
			self::$options['update_schedule'] :
			self::DEFAULT_UPDATE_SCHEDULE;
	}

	/**
	 * Get default endpoint URL base for the official H5P Content Type Hub.
	 *
	 * @return string Default endpoint URL base.
	 */
	public static function get_default_endpoint_url_base() {
		return self::DEFAULT_ENDPOINT_URL_BASE;
	}

	/**
	 * Init function for the class.
	 *
	 * @since 0.1.0
	 */
	public static function init() {
		self::$options = get_option( self::$option_slug, false );
	}
}
Options::init();

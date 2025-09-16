<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds settings page.
 *
 * @since 0.5.0
 */
class Settings {
	/**
	 * The default options.
	 *
	 * @since 0.5.0
	 *
	 * @var array
	 */
	protected $defaults;

	/**
	 * The options.
	 *
	 * @since 0.5.0
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'admin_menu', [ $this, 'add_menu_item' ], 12 );
		add_action( 'admin_init', [ $this, 'init' ] );
		add_filter( 'plugin_action_links_mai-performance-images/mai-performance-images.php', [ $this, 'add_plugin_links' ], 10, 4 );
	}

	/**
	 * Adds menu item for settings page.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function add_menu_item() {
		add_options_page(
			__( 'Mai Performance Images', 'mai-performance-images' ), // page_title
			__( 'Mai Perf Images', 'mai-performance-images' ), // menu_title
			'manage_options', // capability
			'mai-performance-images', // menu_slug
			[ $this, 'add_content' ], // callback
		);
	}

	/**
	 * Adds setting page content.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function add_content() {
		echo '<div class="wrap">';
			printf( '<h2>%s</h2>', __( 'Mai Performance Images', 'mai-performance-images' ) );
			printf( '<p>%s</p>', __( 'Configure image optimization settings for better performance.', 'mai-performance-images' ) );
			echo '<form method="post" action="options.php">';
				settings_fields( 'mai_performance_images_group' );
				do_settings_sections( 'mai-performance-images-section' );
				submit_button();
			echo '</form>';
		echo '</div>';
	}

	/**
	 * Initialize the settings.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function init() {
		// Set defaults/options.
		$this->defaults = get_default_options();
		$this->options  = get_option( 'mai_performance_images', $this->defaults );
		$this->options  = wp_parse_args( $this->options, $this->defaults );

		// Register setting.
		register_setting(
			'mai_performance_images_group', // option_group
			'mai_performance_images', // option_name
			[ $this, 'sanitize' ] // sanitize_callback
		);

		/************
		 * Sections *
		 ************/

		// Register section.
		add_settings_section(
			'mai_performance_images_general', // id
			'', // title
			[ $this, 'general_section_callback' ], // callback
			'mai-performance-images-section' // page
		);

		/************
		 * Fields   *
		 ************/

		// Attributes.
		add_settings_field(
			'attributes', // id
			__( 'Attributes', 'mai-performance-images' ), // title
			[ $this, 'attributes_callback' ], // callback
			'mai-performance-images-section', // page
			'mai_performance_images_general' // section
		);

		// Conversion.
		add_settings_field(
			'conversion', // id
			__( 'Conversion', 'mai-performance-images' ), // title
			[ $this, 'conversion_callback' ], // callback
			'mai-performance-images-section', // page
			'mai_performance_images_general' // section
		);

		// Quality.
		add_settings_field(
			'quality', // id
			__( 'Image Quality', 'mai-performance-images' ), // title
			[ $this, 'quality_callback' ], // callback
			'mai-performance-images-section', // page
			'mai_performance_images_general' // section
		);

		// Cache Duration.
		add_settings_field(
			'cache_duration', // id
			__( 'Cache Duration', 'mai-performance-images' ), // title
			[ $this, 'cache_duration_callback' ], // callback
			'mai-performance-images-section', // page
			'mai_performance_images_general' // section
		);
	}

	/**
	 * General section callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function general_section_callback() {
		?>
		<style>
		.form-table:has(input[name="mai_performance_images[conversion]"]:not(:checked)) {
			tr:has(input[name="mai_performance_images[quality]"]),
			tr:has(input[name="mai_performance_images[cache_duration]"]) {
				display: none;
			}
		}
		</style>
		<?php
	}

	/**
	 * Attributes field callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function attributes_callback() {
		$attributes = $this->options['attributes'];
		?>
		<label>
			<input type="checkbox" name="mai_performance_images[attributes]" value="1" <?php checked( $attributes, 1 ); ?> />
			<?php _e( 'Enable lazy/eager/priority attributes for images', 'mai-performance-images' ); ?>
		</label>
		<p class="description"><?php _e( 'Adds Customizer and block settings for image lazy/eager loading.', 'mai-performance-images' ); ?></p>
		<?php
	}

	/**
	 * Conversion field callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function conversion_callback() {
		$conversion = $this->options['conversion'];
		?>
		<label>
			<input type="checkbox" name="mai_performance_images[conversion]" value="1" <?php checked( $conversion, 1 ); ?> />
			<?php _e( 'Enable image conversion to WebP', 'mai-performance-images' ); ?>
		</label>
		<p class="description"><?php _e( 'Images will be converted to appropriately sized WebP images on the fly and stored in `wp-content/uploads/mai-performance-images` directory.', 'mai-performance-images' ); ?></p>
		<?php
	}

	/**
	 * WebP quality field callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function quality_callback() {
		$quality = $this->options['quality'];
		?>
		<input type="number" name="mai_performance_images[quality]" value="<?php echo esc_attr( $quality ); ?>" min="1" max="100" />
		<p class="description"><?php _e( 'WebP image quality (1-100). Higher values mean better quality but larger file sizes. Default is 80. Changing this value will not affect existing images until they are regenerated after the cache duration expires. Delete the `mai-performance-images` directory to force regeneration of all images.', 'mai-performance-images' ); ?></p>
		<?php
	}

	/**
	 * Cache duration field callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function cache_duration_callback() {
		$cache_duration = $this->options['cache_duration'];
		?>
		<input type="number" name="mai_performance_images[cache_duration]" value="<?php echo esc_attr( $cache_duration ); ?>" min="1" max="365" />
		<p class="description"><?php _e( 'Number of days to keep cached images before they are automatically deleted and regenerated. Default is 30 days and max is 365 days. This helps manage disk space by removing old, unused images.', 'mai-performance-images' ); ?></p>
		<?php
	}

	/**
	 * Sanitize the settings.
	 *
	 * @since 0.5.0
	 *
	 * @param array $input The input array.
	 *
	 * @return array The sanitized array.
	 */
	function sanitize( $input ) {
		$sanitized = [];

		// Sanitize. The boolean fields are not in the input array if they are not set (unchecked).
		$sanitized['conversion']     = isset( $input['conversion'] ) ? rest_sanitize_boolean( $input['conversion'] ) : false;
		$sanitized['attributes']     = isset( $input['attributes'] ) ? rest_sanitize_boolean( $input['attributes'] ) : false;
		$sanitized['quality']        = isset( $input['quality'] ) ? max( 1, min( 100, (int) $input['quality'] ) ) : $this->defaults['quality'];
		$sanitized['cache_duration'] = isset( $input['cache_duration'] ) ? max( 1, min( 365, (int) $input['cache_duration'] ) ) : $this->defaults['cache_duration'];

		return $sanitized;
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 0.5.0
	 *
	 * @param array  $actions     An array of plugin action links.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data from the plugin file headers.
	 * @param string $context     Plugin status context, ie 'all', 'active', 'inactive', 'recently_active'.
	 *
	 * @return array associative array of plugin action links.
	 */
	function add_plugin_links( $actions, $plugin_file, $plugin_data, $context ) {
		$actions['settings'] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=mai-performance-images' ) ), __( 'Settings', 'mai-performance-images' ) );

		return $actions;
	}
}
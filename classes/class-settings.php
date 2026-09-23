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
	 * Construct the class.
	 *
	 * @since 0.5.0
	 */
	function __construct() {
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
			__( 'Performance Images', 'mai-performance-images' ), // menu_title
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
			printf( '<h2>%s</h2>', esc_html__( 'Mai Performance Images', 'mai-performance-images' ) );
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
		register_setting(
			'mai_performance_images_group', // option_group
			'mai_performance_images', // option_name
			[ $this, 'sanitize' ] // sanitize_callback
		);

		add_settings_section(
			'mai_performance_images_general', // id
			'', // title
			'__return_empty_string', // callback
			'mai-performance-images-section' // page
		);

		add_settings_field(
			'attributes', // id
			__( 'Image loading', 'mai-performance-images' ), // title
			[ $this, 'attributes_callback' ], // callback
			'mai-performance-images-section', // page
			'mai_performance_images_general' // section
		);
	}

	/**
	 * Attributes field callback.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	function attributes_callback() {
		?>
		<label>
			<input type="checkbox" name="mai_performance_images[attributes]" value="1" <?php checked( is_attributes_enabled() ); ?> />
			<?php esc_html_e( 'Load the first images right away and the rest as visitors scroll', 'mai-performance-images' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Also adds an Image Loading choice to image blocks, Mai grids and the Customizer.', 'mai-performance-images' ); ?></p>
		<?php
	}

	/**
	 * Sanitize the settings.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $input The input array.
	 *
	 * @return array The sanitized array.
	 */
	function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];

		// An unchecked box is not in the input at all.
		return [
			'attributes' => isset( $input['attributes'] ) && rest_sanitize_boolean( $input['attributes'] ),
		];
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
		$custom = [
			'settings' => sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=mai-performance-images' ) ), __( 'Settings', 'mai-performance-images' ) ),
		];

		return array_merge( $custom, $actions );
	}
}

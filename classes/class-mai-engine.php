<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds the Image Loading settings to Mai Theme.
 *
 * The settings go on Content Archives and Single Content in the Customizer, and on
 * the Mai Post Grid and Mai Term Grid blocks. MaiEntryLoading reads them while each
 * entry renders.
 *
 * @since 0.1.0
 */
class MaiEngine {
	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'mai_content_archive_settings', [ $this, 'add_archive_settings' ], 10, 2 );
		add_filter( 'mai_single_content_settings',  [ $this, 'add_single_settings' ], 10, 2 );
		add_action( 'acf/init',                     [ $this, 'add_grid_block_field_group' ] );
		add_filter( 'mai_grid_args',                [ $this, 'add_grid_args' ] );
	}

	/**
	 * Add archive settings.
	 *
	 * @since 0.3.0
	 *
	 * @param array $settings The settings.
	 * @param string $name The name.
	 *
	 * @return array
	 */
	public function add_archive_settings( array $settings, string $name ): array {
		// Loop through settings.
		foreach ( $settings as $index => $setting ) {
			if ( ! isset( $setting['settings'] ) || 'image_width' !== $setting['settings'] ) {
				continue;
			}

			// Build new settings.
			$new = [
				[
					'settings'       => 'image_loading',
					'label'          => esc_html__( 'Image Loading', 'mai-performance-images' ),
					'type'           => 'select',
					'default'        => '',
					'choices'        => [
						''      => esc_html__( 'Automatic', 'mai-performance-images' ),
						'lazy'  => esc_html__( 'Lazy (loads when scrolled near)', 'mai-performance-images' ),
						'eager' => esc_html__( 'Eager (loads right away)', 'mai-performance-images' ),
					],
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
					],
				],
				[
					'settings'        => 'image_loading_count',
					'label'           => esc_html__( 'Image Loading Count', 'mai-performance-images' ),
					'description'     => esc_html__( 'How many entries load their image right away. The rest load as visitors scroll. Leave empty to load them all right away.', 'mai-performance-images' ),
					'type'            => 'text',
					'sanitize'        => 'absint',
					'default'         => '',
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
						[
							'setting'  => 'image_loading',
							'operator' => '==',
							'value'    => 'eager',
						],
					],
				],
			];

			// Insert the new setting after the current setting.
			array_splice( $settings, $index + 1, 0, $new );
			break;
		}

		// Reindex settings.
		$settings = array_values( $settings );

		return $settings;
	}

	/**
	 * Add single settings.
	 *
	 * @since 0.3.0
	 *
	 * @param array $settings The settings.
	 * @param string $name The name.
	 *
	 * @return array
	 */
	public function add_single_settings( array $settings, string $name ): array {
		// Loop through settings.
		foreach ( $settings as $index => $setting ) {
			if ( ! isset( $setting['settings'] ) || 'image_size' !== $setting['settings'] ) {
				continue;
			}

			// Build new settings.
			$new = [
				[
					'settings'       => 'image_loading',
					'label'          => esc_html__( 'Image Loading', 'mai-performance-images' ),
					'type'           => 'select',
					'default'        => '',
					'choices'        => [
						''      => esc_html__( 'Automatic', 'mai-performance-images' ),
						'lazy'  => esc_html__( 'Lazy (loads when scrolled near)', 'mai-performance-images' ),
						'eager' => esc_html__( 'Eager (loads right away)', 'mai-performance-images' ),
					],
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
					],
				],
			];

			// Insert the new setting after the current setting.
			array_splice( $settings, $index + 1, 0, $new );
			break;
		}

		// Reindex settings.
		$settings = array_values( $settings );

		return $settings;
	}

	/**
	 * Register grid block field group.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_grid_block_field_group(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		/** @disregard P1010 */
		\acf_add_local_field_group(
			[
				'key'   => 'mai_performance_images_grid_block_field_group',
				'title' => esc_html__( 'Mai Performance Images', 'mai-performance-images' ),
				'fields' => [
					[
						'label'        => __( 'Mai Performance Images', 'mai-performance-images' ),
						'key'          => 'field_63f9a2b4c8d1e',
						'type'         => 'accordion',
						'open'         => 0,
						'multi_expand' => 1,
						'endpoint'     => 0,
					],
					[
						'key'     => 'field_63f9a2b4c8d2e',
						'name'    => 'image_loading',
						'label'   => esc_html__( 'Image Loading', 'mai-performance-images' ),
						'type'    => 'select',
						'choices' => [
							''      => esc_html__( 'Automatic', 'mai-performance-images' ),
							'lazy'  => esc_html__( 'Lazy (loads when scrolled near)', 'mai-performance-images' ),
							'eager' => esc_html__( 'Eager (loads right away)', 'mai-performance-images' ),
						],
						'conditional_logic' => [
							[
								'field'    => 'mai_grid_block_show',
								'operator' => '==',
								'value'    => 'image',
							],
						],
					],
					[
						'key'               => 'field_63f9a2b4c8d3e',
						'name'              => 'image_loading_count',
						'label'             => esc_html__( 'Image Loading Count', 'mai-performance-images' ),
						'instructions'      => esc_html__( 'How many entries load their image right away. The rest load as visitors scroll. Leave empty to load them all right away.', 'mai-performance-images' ),
						'type'              => 'number',
						'conditional_logic' => [
							[
								'field'    => 'mai_grid_block_show',
								'operator' => '==',
								'value'    => 'image',
							],
							[
								'field'    => 'field_63f9a2b4c8d2e',
								'operator' => '==',
								'value'    => 'eager',
							],
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-post-grid',
						],
					],
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-term-grid',
						],
					],
				],
				'menu_order' => 10,
				'active'     => true,
			]
		);
	}

	/**
	 * Add grid args.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args The args.
	 *
	 * @return array
	 */
	public function add_grid_args( array $args ): array {
		/** @disregard P1010 */
		$args['image_loading'] = \get_field( 'image_loading' );
		/** @disregard P1010 */
		$args['image_loading_count'] = \get_field( 'image_loading_count' );

		return $args;
	}
}

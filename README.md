# Mai Performance Images

A WordPress plugin that adds dynamic image loading to the block editor, optimizing image delivery through automatic resizing and WebP conversion.

## Description

Mai Performance Images handles image optimization in WordPress by:
1. Dynamically resizing images based on viewport size
2. Converting images to WebP format
3. Processing images in the background
4. Supporting lazy loading configuration in the block editor

## Installation

1. Upload the plugin files to `/wp-content/plugins/mai-performance-images`
2. Activate the plugin through the WordPress admin interface
3. Images will automatically be processed when loaded through supported blocks

## Requirements

- WordPress 6.7+
- PHP 8.2+

## Extending the Plugin

### Creating a Custom Image Handler

You can extend the base `Images` class to add support for custom blocks or modify existing image handling. Here's an example:

```php
<?php

namespace YourNamespace;

use Mai\PerformanceImages\Images;

class CustomImages extends Images {
	/**
	 * Add hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function hooks(): void {
		// Add your custom block filters
		add_filter( 'render_block_acf/gallery', [ $this, 'render_acf_gallery_block' ], 99, 2 );
		add_filter( 'render_block_custom/image', [ $this, 'render_custom_image_block' ], 99, 2 );
	}

	/**
	 * Process an ACF gallery block.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string
	 */
	public function render_acf_gallery_block( string $block_content, array $block ): string {
		// Bail if no content
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get alignment args from parent method
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add custom sizes for gallery
		$args['sizes'] = [
			'mobile'  => '(max-width: 599px) 100vw',
			'tablet'  => '(max-width: 1199px) 50vw',
			'desktop' => 'calc(33.333% - 20px)',
		];

		// Process each image in gallery
		return $this->handle_image( $block_content, $args );
	}

	/**
	 * Process a custom image block.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string
	 */
	public function render_custom_image_block( string $block_content, array $block ): string {
		// Bail if no content
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get custom image ID from block attributes
		$image_id = $block['attrs']['imageId'] ?? null;

		// Get alignment args from parent method
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add image ID to args
		$args['image_id'] = $image_id;

		// Add custom sizes
		$args['sizes'] = [
			'mobile'  => '90vw',
			'tablet'  => '80vw',
			'desktop' => '1200px',
		];

		// Process the image
		return $this->handle_image( $block_content, $args );
	}
}
```

### Registering Your Custom Handler

Add your custom image handler in your theme's `functions.php` or plugin file:

```php
add_action( 'init', function() {
    new \YourNamespace\CustomImages();
}, 20 );
```

### Available Methods

When extending the `Images` class, you have access to these protected methods:

1. `get_alignment_args( string $align ): array`
   - Gets default responsive sizes based on alignment
   - Parameters:
     - `$align`: Block alignment ('full', 'wide', or default)
   - Returns array with `max_width` and `sizes`

2. `handle_image( string $html, array $args ): string`
   - Processes an image tag for responsive loading
   - Parameters:
     - `$html`: The HTML containing the image tag
     - `$args`: Configuration array with:
       - `max_width`: Maximum image width
       - `src_width`: Width for src attribute
       - `image_id`: WordPress attachment ID
       - `sizes`: Responsive sizes configuration

### Built-in Block Support

The plugin automatically handles these core blocks:
- `core/cover`
- `core/image`
- `core/post-featured-image`
- `core/media-text`
- `core/site-logo`

## Advanced Configuration

### Available Filters

1. `mai_performance_images_image_attributes`
   - Modifies image attributes before processing
   - Parameters:
     - `array $attr`: Image attributes array containing:
       - `src`: Source URL
       - `srcset`: Responsive image srcset
       - `sizes`: Responsive sizes string
     - `array $args`: Processing arguments

2. `mai_performance_images_quality`
   - Customizes WebP image quality
   - Default: 80
```php
add_filter( 'mai_performance_images_quality', function( $quality ) {
	return 85; // Adjust quality (0-100)
});
```

3. `mai_performance_images_max_retries`
   - Controls how many times to retry failed image processing
   - Default: 3
```php
add_filter( 'mai_performance_images_max_retries', function( $retries ) {
	return 5; // Adjust retry attempts
});
```

### Debugging

The plugin includes a Logger class that helps with debugging. Enable WordPress debug mode to see logs:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Basic Auth Configuration

If your site is protected by Basic Auth (e.g., on a staging environment), you'll need to configure the plugin to work with your existing Basic Auth credentials. Add these constants to your `wp-config.php`:

```php
// Basic Auth credentials for background processing
define( 'MAI_BASIC_AUTH_USERNAME', 'your_existing_username' );
define( 'MAI_BASIC_AUTH_PASSWORD', 'your_existing_password' );
```

These credentials should match your site's existing Basic Auth configuration. The plugin will use these credentials to authenticate background processing requests.

## License

GPL-2.0-or-later
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { SelectControl } from '@wordpress/components';

/**
 * Blocks that allow img loading attribute settings.
 *
 * @since 0.2.0
 *
 * @type {Array}
 */
const allowedBlocks = [ 'core/cover', 'core/image', 'core/site-logo', 'core/post-featured-image', 'core/media-text' ];

/**
 * Add custom img loading attribute to allowed blocks.
 *
 * @since 0.2.0
 *
 * @param {Object} settings Original block settings.
 * @param {string} name     Block name.
 *
 * @return {Object} Modified block settings.
 */
addFilter(
	'blocks.registerBlockType',
	'mai/add-loading-attribute',
	(settings, name) => {
		if (!allowedBlocks.includes(name)) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				imgLoading: {
					type: 'string',
					default: '',
				},
			},
		};
	}
);

/**
 * Add img loading attribute control to block inspector settings.
 *
 * Adds a select control to allowed blocks that allows users to choose
 * between default, lazy, and eager loading strategies for images.
 *
 * @since 0.2.0
 */
addFilter(
	'editor.BlockEdit',
	'mai/with-loading-attribute',
	createHigherOrderComponent((BlockEdit) => (props) => {
		if (!allowedBlocks.includes(props.name)) {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<div style={{ padding: '0px 16px 8px' }}>
						<SelectControl
							label={__('Image Loading')}
							value={attributes.imgLoading || ''}
							options={[
								{ label: __('Default'), value: '' },
								{ label: __('Lazy (for offscreen images)'), value: 'lazy' },
								{ label: __('Eager (loads immediately)'), value: 'eager' },
							]}
							onChange={(value) => setAttributes({ imgLoading: value })}
							help={__('Controls how the browser loads this image.')}
						/>
					</div>
				</InspectorControls>
			</>
		);
	}, 'withLoadingAttribute')
);

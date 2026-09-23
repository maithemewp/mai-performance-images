<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Answers the loading value for a Mai entry's own image.
 *
 * Each Mai entry, whether an archive entry, the single entry or a grid entry,
 * carries the Image Loading setting of whatever rendered it, and knows its position
 * in its loop. That is only knowable while the entry renders, so this keeps track
 * of the entry being rendered and answers for its image.
 *
 * Entries and loops nest: a grid can sit in a single entry's content. Both are kept
 * as stacks, so the innermost entry answers and nothing outlives its entry.
 *
 * @since 0.7.0
 */
final class MaiEntryLoading {
	/**
	 * One entry count per open loop.
	 *
	 * @since 0.7.0
	 *
	 * @var int[]
	 */
	private array $loops = [];

	/**
	 * The entries being rendered, innermost last. Each holds its args and position.
	 *
	 * @since 0.7.0
	 *
	 * @var array[]
	 */
	private array $entries = [];

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'genesis_markup_entries_open',          [ $this, 'open_loop' ], 10, 2 );
		add_filter( 'genesis_markup_entries_close',         [ $this, 'close_loop' ], 10, 2 );
		add_action( 'mai_before_entry',                     [ $this, 'open_entry' ], 10, 2 );
		add_action( 'mai_after_entry',                      [ $this, 'close_entry' ], 10, 0 );
		add_filter( 'mai_performance_images_entry_loading', [ $this, 'get_loading' ], 10, 1 );
	}

	/**
	 * Starts counting entries for a loop.
	 *
	 * Genesis runs both the open and close filters on every call, so only a call
	 * that opens the tag counts.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $open The opening markup.
	 * @param mixed $args The markup args.
	 *
	 * @return mixed
	 */
	public function open_loop( $open, $args ) {
		if ( ! empty( $args['open'] ) ) {
			$this->loops[] = 0;
		}

		return $open;
	}

	/**
	 * Stops counting entries for a loop.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $close The closing markup.
	 * @param mixed $args  The markup args.
	 *
	 * @return mixed
	 */
	public function close_loop( $close, $args ) {
		if ( ! empty( $args['close'] ) ) {
			array_pop( $this->loops );
		}

		return $close;
	}

	/**
	 * Records the entry about to render.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $entry The entry.
	 * @param mixed $args  The entry args.
	 *
	 * @return void
	 */
	public function open_entry( $entry, $args ): void {
		$position = 1;

		if ( $this->loops ) {
			$key      = array_key_last( $this->loops );
			$position = ++$this->loops[ $key ];
		}

		$this->entries[] = [
			'args'     => is_array( $args ) ? $args : [],
			'position' => $position,
		];
	}

	/**
	 * Forgets the entry that just rendered.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function close_entry(): void {
		array_pop( $this->entries );
	}

	/**
	 * Returns the entry's loading choice for its image.
	 *
	 * Lazy applies to every entry. Eager applies to the first N entries when a
	 * count is set, and to all of them when it is not.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $loading The loading value so far.
	 *
	 * @return mixed
	 */
	public function get_loading( $loading ) {
		$entry = $this->entries ? end( $this->entries ) : null;

		if ( ! $entry ) {
			return $loading;
		}

		$setting = (string) ( $entry['args']['image_loading'] ?? '' );

		if ( ! in_array( $setting, [ 'lazy', 'eager' ], true ) ) {
			return $loading;
		}

		$count = absint( $entry['args']['image_loading_count'] ?? 0 );

		if ( 'eager' === $setting && $count && $entry['position'] > $count ) {
			return 'lazy';
		}

		return $setting;
	}
}

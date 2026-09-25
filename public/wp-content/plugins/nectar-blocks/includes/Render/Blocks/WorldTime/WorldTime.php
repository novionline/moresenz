<?php

namespace Nectar\Render\Blocks\WorldTime;

use Nectar\Utilities\BlockAnimations;

/**
 * WorldTime Block Rendering
 * @version 3.0.0
 * @since 3.0.0
 */
class WorldTime {
	private $block_attributes;

	private const DEFAULT_TIMEZONE = 'America/New_York';

	public $block_class_name = 'nectar-blocks-world-time';

	function __construct($block_attributes, $content) {
		$this->block_attributes = $block_attributes;
	}

	/**
	 * Get a valid DateTimeZone, defaulting to New York if invalid
	 */
	private function get_timezone($timezone) {
		if (empty($timezone) || ! is_string($timezone)) {
			return new \DateTimeZone(self::DEFAULT_TIMEZONE);
		}

		try {
			return new \DateTimeZone($timezone);
		} catch (\Exception $e) {
			return new \DateTimeZone(self::DEFAULT_TIMEZONE);
		}
	}

	/**
	 * Get typography class name
	 */
	private function typography_class_name($typography) {
		if (! $typography) {
			return '';
		}

		if (strpos($typography, 'nectar-gt') !== false) {
			return esc_attr($typography);
		}
		return $typography !== '' ? 'nectar-font-' . esc_attr($typography) : '';
	}

	/**
	 * Get all block class names
	 */
	public function get_class_names() {
		$classnames = [$this->block_class_name];

		// Typography
		if (! empty($this->block_attributes['typography'])) {
			$classnames[] = $this->typography_class_name($this->block_attributes['typography']);
		}

		// Font color
		if (! empty($this->block_attributes['fontColor']['desktop']['solidValue'])) {
			$classnames[] = 'has-font-color';
		}

		// Global typography
		if (! empty($this->block_attributes['typography']) && $this->block_attributes['typography'] !== 'em') {
			$classnames[] = 'has-global-typography';
		}

		// Line height
		if (isset($this->block_attributes['fontSettings']['desktop']['lineHeight']['disabled']) &&
			! $this->block_attributes['fontSettings']['desktop']['lineHeight']['disabled']) {
			$classnames[] = 'has-line-height';
		}

		// Alignment
		if (! empty($this->block_attributes['fontSettings']['desktop']['align'])) {
			$classnames[] = 'has-alignment-set';
		}

		return implode(' ', $classnames);
	}

	/**
	 * Format date with localization support
	 */
	private function format_date($timezone, $format) {
		$date_time = new \DateTime('now', $this->get_timezone($timezone));
		$timestamp = $date_time->getTimestamp() + $date_time->getOffset();

		switch ($format) {
			case 'short':
				// "Jan 15"
				return wp_date('M j', $timestamp);
			case 'medium':
				// "Jan 15, 2024"
				return wp_date('M j, Y', $timestamp);
			case 'long':
				// "15 Jan 2024"
				return wp_date('j M Y', $timestamp);
			case 'numeric':
				// "01/15/2024"
				return wp_date('m/d/Y', $timestamp);
			case 'weekday':
				// "Sun, Jan 15"
				return wp_date('D, M j', $timestamp);
			case 'weekday-numeric':
				// "Sunday, 02/01/26"
				return wp_date('l, m/d/y', $timestamp);
			default:
				return wp_date('M j, Y', $timestamp);
		}
	}

	/**
	 * Format time
	 */
	private function format_time($timezone, $format, $show_seconds) {
		$date_time = new \DateTime('now', $this->get_timezone($timezone));

		if ($format === '12h') {
			$time_format = $show_seconds ? 'g:i:s A' : 'g:i A';
		} else {
			$time_format = $show_seconds ? 'H:i:s' : 'H:i';
		}

		return $date_time->format($time_format);
	}

	/**
	 * Get timezone abbreviation
	 */
	private function get_timezone_name($timezone) {
		$date_time = new \DateTime('now', $this->get_timezone($timezone));
		return $date_time->format('T');
	}

	/**
	 * Render the block
	 */
	public function render() {
		$city = $this->block_attributes['city'] ?? 'America/New_York';
		$time_format = $this->block_attributes['timeFormat'] ?? '12h';
		$date_format = $this->block_attributes['dateFormat'] ?? 'medium';
		$date_position = $this->block_attributes['datePosition'] ?? 'after';
		$date_size = $this->block_attributes['dateSize'] ?? 'same';
		$show_seconds = $this->block_attributes['showSeconds'] ?? false;
		$show_date = $this->block_attributes['showDate'] ?? true;
		$show_timezone = $this->block_attributes['showTimezone'] ?? true;

		// Format time, date, and timezone
		$time = $this->format_time($city, $time_format, $show_seconds);
		$date = $show_date ? $this->format_date($city, $date_format) : '';
		$timezone_name = ($show_date && $show_timezone) ? $this->get_timezone_name($city) : '';

		// Build root classes (merging __inner onto root)
		$root_classes = $this->get_class_names();
		$root_classes .= ' ' . $this->block_class_name . '__inner';
		if ($date_position === 'before') {
			$root_classes .= ' ' . $this->block_class_name . '__inner--date-before';
		}
		if ($date_size === 'smaller') {
			$root_classes .= ' ' . $this->block_class_name . '__inner--date-smaller';
		}

		// Animation attributes
		$animation_attrs = '';
		if (isset($this->block_attributes['animation'])) {
			$animation_attrs = BlockAnimations::get_animation_attrs($this->block_attributes['animation']);
		}

		// Build output
		$output = '';

		// Date before time
		if ($show_date && $date_position === 'before') {
			$output .= '<span class="' . esc_attr($this->block_class_name . '__date') . '">' . esc_html($date) . '</span>';
		}

		// Time
		$output .= '<span class="' . esc_attr($this->block_class_name . '__time') . '">' . esc_html($time) . '</span>';

		// Date after time
		if ($show_date && $date_position === 'after') {
			$output .= '<span class="' . esc_attr($this->block_class_name . '__date') . '">' . esc_html($date) . '</span>';
		}

		// Timezone
		if ($show_date && $show_timezone) {
			$output .= '<span class="' . esc_attr($this->block_class_name . '__timezone') . '">' . esc_html($timezone_name) . '</span>';
		}

		// Data attributes for JS live updates (on root element)
		$data_attrs = sprintf(
		    'data-timezone="%s" data-format="%s" data-date-format="%s" data-date-position="%s" data-show-seconds="%s" data-show-date="%s" data-show-timezone="%s"',
		    esc_attr($city),
		    esc_attr($time_format),
		    esc_attr($date_format),
		    esc_attr($date_position),
		    esc_attr($show_seconds ? 'true' : 'false'),
		    esc_attr($show_date ? 'true' : 'false'),
		    esc_attr($show_timezone ? 'true' : 'false')
		);

		$block_id = $this->block_attributes['blockId'] ?? '';
		if (empty($block_id)) {
			$block_id = 'nectar-blocks-world-time-' . wp_unique_id();
		}

		return sprintf(
		    '<div id="%s" class="%s" %s%s>%s</div>',
		    esc_attr($block_id),
		    esc_attr($root_classes),
		    $data_attrs,
		    $animation_attrs,
		    $output
		);
	}
}

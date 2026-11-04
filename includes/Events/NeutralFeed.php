<?php
/**
 * Collects neutral event records during one pull.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * In-request list of neutral events.
 *
 * Formatters call add() on every record. The call does nothing unless a
 * native import started collection, so The Events Calendar path is unchanged.
 *
 * @since 1.2.0
 */
class NeutralFeed {

	/**
	 * Whether the current pull should keep neutral records.
	 *
	 * @var bool
	 */
	protected static $collecting = false;

	/**
	 * Records captured since start().
	 *
	 * @var array
	 */
	protected static $events = array();

	/**
	 * Begin a native import.
	 *
	 * @return void
	 */
	public static function start() {
		self::$collecting = true;
		self::$events     = array();
	}

	/**
	 * Keep one neutral record when collection is on.
	 *
	 * @param array $event Neutral event record.
	 * @return void
	 */
	public static function add( array $event ) {
		if ( ! self::$collecting ) {
			return;
		}

		self::$events[] = $event;
	}

	/**
	 * Stop collection and return what was captured.
	 *
	 * @return array
	 */
	public static function finish() {
		$events           = self::$events;
		self::$collecting = false;
		self::$events     = array();

		return $events;
	}
}

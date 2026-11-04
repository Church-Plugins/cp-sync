<?php
/**
 * Turn neutral event records into one post per date, plus series.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Upsert, series grouping, and removal of dropped future dates.
 *
 * Planning Center instances are one date each. A Planning Center Event with
 * two or more instances is a series. A Church Community Builder event id with
 * two or more listing rows is a series. One date never gets a new series
 * post. An existing series stays published when the feed still has one
 * of its dates, so past dates keep their series page.
 *
 * @since 1.2.0
 */
class EventSync {

	/**
	 * Apply one feed to the store.
	 *
	 * An empty feed does not remove anything. That matches the guard on the
	 * other imports: a failed fetch must not look like "everything was deleted".
	 *
	 * @param array      $neutral_events Neutral records from the ChMS formatters.
	 * @param EventStore $store          Where posts are read and written.
	 * @param string     $now_utc        Current UTC `Y-m-d H:i:s`.
	 * @param string     $timezone       WordPress timezone for the stored local values.
	 * @return array Summary counts.
	 */
	public static function sync( array $neutral_events, EventStore $store, $now_utc, $timezone = 'UTC' ) {
		$normalized = array();

		foreach ( $neutral_events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$row = self::normalize( $event, $timezone );

			if ( null !== $row ) {
				$normalized[] = $row;
			}
		}

		$existing_events = $store->events();

		if ( empty( $normalized ) && ! empty( $existing_events ) ) {
			self::log( 'Skipping event display sync: the feed was empty while events are already stored.' );

			return array(
				'skipped'            => true,
				'events'             => 0,
				'series'             => 0,
				'trashed_events'     => 0,
				'trashed_series'     => 0,
				'removed_source_ids' => array(),
			);
		}

		$grouped          = self::group( $normalized );
		$existing_series  = $store->series();
		$providers        = array();
		$series_post_ids  = array();
		$series_by_key    = array();
		$events_by_key    = array();
		$seen             = array();
		$trashed_events      = 0;
		$trashed_series      = 0;
		$removed_source_ids  = array();

		foreach ( $normalized as $row ) {
			$providers[ $row['provider'] ] = true;
		}

		foreach ( $existing_series as $row ) {
			if ( empty( $row['series_key'] ) ) {
				continue;
			}
			$series_by_key[ $row['series_key'] ] = $row;
		}

		foreach ( $grouped['series'] as $key => $record ) {
			if ( isset( $series_by_key[ $key ] ) ) {
				if ( ! empty( $series_by_key[ $key ]['locked'] ) ) {
					$series_post_ids[ $key ] = (int) $series_by_key[ $key ]['post_id'];
					continue;
				}
				$record['post_id'] = (int) $series_by_key[ $key ]['post_id'];
			}

			$series_post_ids[ $key ] = (int) $store->save_series( $record );
		}

		foreach ( $grouped['events'] as $record ) {
			$ident = self::identity( $record['provider'], $record['occurrence_id'] );

			if ( '' !== $ident ) {
				$seen[ $ident ] = true;
			}
		}

		$referenced_series = self::referenced_series_ids( $existing_events, $seen, $providers, $now_utc );

		foreach ( $existing_series as $row ) {
			$key = isset( $row['series_key'] ) ? (string) $row['series_key'] : '';

			if ( '' === $key || isset( $grouped['series'][ $key ] ) ) {
				continue;
			}

			if ( ! empty( $row['locked'] ) || 'trash' === ( $row['status'] ?? '' ) ) {
				continue;
			}

			if ( empty( $providers[ $row['provider'] ?? '' ] ) ) {
				continue;
			}

			// A past date that is no longer in the feed still points at this
			// series through _cp_series_id. Leave the series published until
			// no synced date references it.
			if ( ! empty( $referenced_series[ (int) $row['post_id'] ] ) ) {
				continue;
			}

			$store->trash_series( $row['post_id'] );
			if ( isset( $series_by_key[ $key ] ) ) {
				$series_by_key[ $key ]['status'] = 'trash';
			}
			$trashed_series++;
		}

		foreach ( $existing_events as $row ) {
			$ident = self::identity( $row['provider'] ?? '', $row['occurrence_id'] ?? '' );

			if ( '' === $ident ) {
				continue;
			}

			$events_by_key[ $ident ] = $row;
		}

		foreach ( $grouped['events'] as $record ) {
			$ident = self::identity( $record['provider'], $record['occurrence_id'] );
			$seen[ $ident ] = true;
			$record['series_post_id'] = 0;

			if ( '' !== $record['series_key'] && isset( $series_post_ids[ $record['series_key'] ] ) ) {
				$record['series_post_id'] = (int) $series_post_ids[ $record['series_key'] ];
			} elseif ( '' !== $record['series_key'] && isset( $series_by_key[ $record['series_key'] ] ) && 'trash' !== ( $series_by_key[ $record['series_key'] ]['status'] ?? '' ) ) {
				// A trashed series, including one trashed earlier in this run,
				// must not become _cp_series_id on a new date.
				$record['series_post_id'] = (int) $series_by_key[ $record['series_key'] ]['post_id'];
			}

			if ( isset( $events_by_key[ $ident ] ) ) {
				if ( ! empty( $events_by_key[ $ident ]['locked'] ) ) {
					continue;
				}
				$record['post_id'] = (int) $events_by_key[ $ident ]['post_id'];
			}

			$store->save_event( $record );
		}

		foreach ( $existing_events as $row ) {
			$provider = isset( $row['provider'] ) ? (string) $row['provider'] : '';
			$ident    = self::identity( $provider, $row['occurrence_id'] ?? '' );

			if ( '' === $ident || isset( $seen[ $ident ] ) ) {
				continue;
			}

			if ( empty( $providers[ $provider ] ) ) {
				continue;
			}

			if ( ! empty( $row['locked'] ) || 'trash' === ( $row['status'] ?? '' ) ) {
				continue;
			}

			$end = isset( $row['end_utc'] ) && '' !== (string) $row['end_utc']
				? (string) $row['end_utc']
				: (string) ( $row['start_utc'] ?? '' );

			if ( '' === $end || strcmp( $end, (string) $now_utc ) >= 0 ) {
				$store->trash_event( $row['post_id'] );
				$trashed_events++;
				$source = isset( $row['source_id'] ) ? trim( (string) $row['source_id'] ) : '';

				if ( '' !== $source ) {
					$removed_source_ids[] = $source;
				}
			}
		}

		return array(
			'skipped'            => false,
			'events'             => count( $grouped['events'] ),
			'series'             => count( $grouped['series'] ),
			'trashed_events'     => $trashed_events,
			'trashed_series'     => $trashed_series,
			'removed_source_ids' => $removed_source_ids,
		);
	}

	/**
	 * Split dates into series (two or more) and standalone dates.
	 *
	 * @param array $rows Normalized rows from normalize().
	 * @return array{events: array, series: array}
	 */
	public static function group( array $rows ) {
		$buckets = array();
		$events  = array();
		$series  = array();

		foreach ( $rows as $row ) {
			$series_id = isset( $row['series_id'] ) ? trim( (string) $row['series_id'] ) : '';

			if ( '' === $series_id ) {
				$row['series_key'] = '';
				$events[]          = $row;
				continue;
			}

			$key               = $row['provider'] . '|' . $series_id;
			$buckets[ $key ][] = $row;
		}

		foreach ( $buckets as $key => $members ) {
			if ( count( $members ) < 2 ) {
				// One feed date does not create a series. It keeps the key so
				// an existing series post stays linked through _cp_series_id.
				$members[0]['series_key'] = $key;
				$events[]                 = $members[0];
				continue;
			}

			foreach ( $members as $member ) {
				$member['series_key'] = $key;
				$events[]             = $member;
			}

			$series[ $key ] = self::series_record( $key, $members );
		}

		return array(
			'events' => $events,
			'series' => $series,
		);
	}

	/**
	 * Map one neutral record onto the fields a post stores.
	 *
	 * Church Community Builder listing rows share an event id, so the
	 * occurrence id adds the UTC start. Planning Center instance ids are
	 * already one per date.
	 *
	 * @param array  $event    Neutral event record.
	 * @param string $timezone WordPress timezone, used when the record has none.
	 * @return array|null
	 */
	public static function normalize( array $event, $timezone = 'UTC' ) {
		$provider = isset( $event['provider'] ) ? trim( (string) $event['provider'] ) : '';
		$chms_id  = isset( $event['chms_id'] ) ? trim( (string) $event['chms_id'] ) : '';
		$start    = isset( $event['start_utc'] ) ? trim( (string) $event['start_utc'] ) : '';
		$end      = isset( $event['end_utc'] ) ? trim( (string) $event['end_utc'] ) : '';

		if ( '' === $provider || '' === $chms_id || '' === $start ) {
			return null;
		}

		if ( '' === $end ) {
			$end = $start;
		}

		$zone = $timezone;

		if ( isset( $event['timezone'] ) && is_string( $event['timezone'] ) && EventTime::is_valid_timezone( $event['timezone'] ) ) {
			$zone = $event['timezone'];
		}

		if ( ! EventTime::is_valid_timezone( $zone ) ) {
			$zone = 'UTC';
		}

		try {
			$start_local = EventTime::to_local( $start, $zone )->format( 'Y-m-d H:i:s' );
			$end_local   = EventTime::to_local( $end, $zone )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return null;
		}

		$series_id = isset( $event['series_id'] ) ? trim( (string) $event['series_id'] ) : '';

		if ( '' === $series_id && 'ccb' === $provider ) {
			$series_id = $chms_id;
		}

		$occurrence_id = 'ccb' === $provider ? $chms_id . '|' . $start : $chms_id;

		return array(
			'provider'         => $provider,
			'occurrence_id'    => $occurrence_id,
			'source_id'        => $chms_id,
			'series_id'        => $series_id,
			'title'            => isset( $event['title'] ) ? (string) $event['title'] : '',
			'content'          => isset( $event['content'] ) ? (string) $event['content'] : '',
			'excerpt'          => isset( $event['excerpt'] ) ? (string) $event['excerpt'] : '',
			'start_utc'        => $start,
			'end_utc'          => $end,
			'start_local'      => $start_local,
			'end_local'        => $end_local,
			'timezone'         => $zone,
			'all_day'          => ! empty( $event['all_day'] ),
			'location'         => self::location_string( $event ),
			'image_url'        => isset( $event['thumbnail_url'] ) ? trim( (string) $event['thumbnail_url'] ) : '',
			'registration_url' => self::registration_url( $event ),
			'categories'       => self::category_names( $event ),
		);
	}

	/**
	 * Category names already present on the neutral record.
	 *
	 * @param array $event Neutral event record.
	 * @return string[]
	 */
	public static function category_names( array $event ) {
		$names = array();

		if ( ! empty( $event['categories'] ) && is_array( $event['categories'] ) ) {
			foreach ( $event['categories'] as $name ) {
				if ( is_string( $name ) && '' !== trim( $name ) ) {
					$names[] = trim( $name );
				}
			}
		}

		if ( empty( $names ) && ! empty( $event['event_category'] ) && is_array( $event['event_category'] ) ) {
			foreach ( $event['event_category'] as $name ) {
				if ( is_string( $name ) && '' !== trim( $name ) ) {
					$names[] = trim( $name );
				}
			}
		}

		if ( empty( $names ) ) {
			$type = $event['meta_input']['event_type'] ?? '';

			if ( is_string( $type ) && '' !== trim( $type ) ) {
				$names[] = trim( $type );
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Location line from the name and any address parts already parsed.
	 *
	 * @param array $event Neutral event record.
	 * @return string
	 */
	public static function location_string( array $event ) {
		$name  = '';
		$venue = ( isset( $event['venue'] ) && is_array( $event['venue'] ) ) ? $event['venue'] : array();

		if ( ! empty( $event['location_name'] ) && is_string( $event['location_name'] ) ) {
			$name = trim( $event['location_name'] );
		} elseif ( ! empty( $venue['venue'] ) && is_string( $venue['venue'] ) ) {
			$name = trim( $venue['venue'] );
		}

		$parts = array();

		foreach ( array( 'address', 'city', 'state', 'zip' ) as $key ) {
			if ( ! empty( $venue[ $key ] ) && is_string( $venue[ $key ] ) ) {
				$parts[] = trim( $venue[ $key ] );
			}
		}

		$address = implode( ', ', array_filter( $parts ) );

		if ( '' !== $name && '' !== $address ) {
			return $name . ', ' . $address;
		}

		return '' !== $name ? $name : $address;
	}

	/**
	 * ChMS URL used by the Register button.
	 *
	 * @param array $event Neutral event record.
	 * @return string
	 */
	public static function registration_url( array $event ) {
		if ( ! empty( $event['registration_url'] ) && is_string( $event['registration_url'] ) ) {
			return trim( $event['registration_url'] );
		}

		if ( ! empty( $event['event_url'] ) && is_string( $event['event_url'] ) ) {
			return trim( $event['event_url'] );
		}

		return '';
	}

	/**
	 * Series fields copied from the dates that belong to it.
	 *
	 * @param string $key     Provider plus series id.
	 * @param array  $members Normalized date rows.
	 * @return array
	 */
	protected static function series_record( $key, array $members ) {
		$first   = $members[0];
		$content = $first['content'];
		$image   = '';
		$url     = '';

		foreach ( $members as $member ) {
			if ( '' === $image && '' !== $member['image_url'] ) {
				$image = $member['image_url'];
			}

			if ( '' === $url && '' !== $member['registration_url'] ) {
				$url = $member['registration_url'];
			}

			if ( strlen( $member['content'] ) > strlen( $content ) ) {
				$content = $member['content'];
			}
		}

		return array(
			'series_key'       => $key,
			'provider'         => $first['provider'],
			'title'            => $first['title'],
			'content'          => $content,
			'image_url'        => $image,
			'registration_url' => $url,
		);
	}

	/**
	 * Series post IDs that a synced date will still reference after this run.
	 *
	 * Dates this sync is about to trash do not count. Past dates that stay
	 * published do, including when they are no longer in the feed.
	 *
	 * @param array  $existing_events Stored date rows.
	 * @param array  $seen            Occurrence identities in this feed.
	 * @param array  $providers       Providers present in this feed.
	 * @param string $now_utc         Current UTC `Y-m-d H:i:s`.
	 * @return array<int,bool>
	 */
	protected static function referenced_series_ids( array $existing_events, array $seen, array $providers, $now_utc ) {
		$ids = array();

		foreach ( $existing_events as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$series_id = isset( $row['series_post_id'] ) ? (int) $row['series_post_id'] : 0;

			if ( $series_id < 1 || ! self::date_remains( $row, $seen, $providers, $now_utc ) ) {
				continue;
			}

			$ids[ $series_id ] = true;
		}

		return $ids;
	}

	/**
	 * Whether this stored date will still be on the site after the removal pass.
	 *
	 * @param array  $row       Stored date row.
	 * @param array  $seen      Occurrence identities in this feed.
	 * @param array  $providers Providers present in this feed.
	 * @param string $now_utc   Current UTC `Y-m-d H:i:s`.
	 * @return bool
	 */
	protected static function date_remains( array $row, array $seen, array $providers, $now_utc ) {
		if ( 'trash' === ( $row['status'] ?? '' ) ) {
			return false;
		}

		$provider = isset( $row['provider'] ) ? (string) $row['provider'] : '';
		$ident    = self::identity( $provider, $row['occurrence_id'] ?? '' );

		if ( '' !== $ident && isset( $seen[ $ident ] ) ) {
			return true;
		}

		if ( ! empty( $row['locked'] ) || empty( $providers[ $provider ] ) ) {
			return true;
		}

		$end = isset( $row['end_utc'] ) && '' !== (string) $row['end_utc']
			? (string) $row['end_utc']
			: (string) ( $row['start_utc'] ?? '' );

		return '' !== $end && strcmp( $end, (string) $now_utc ) < 0;
	}

	/**
	 * Provider plus occurrence id.
	 *
	 * @param string $provider      Provider slug.
	 * @param string $occurrence_id Occurrence id.
	 * @return string
	 */
	protected static function identity( $provider, $occurrence_id ) {
		$provider      = trim( (string) $provider );
		$occurrence_id = trim( (string) $occurrence_id );

		if ( '' === $provider || '' === $occurrence_id ) {
			return '';
		}

		return $provider . '|' . $occurrence_id;
	}

	/**
	 * Write a line to the sync log when the plugin logger is available.
	 *
	 * @param string $message Log line.
	 * @return void
	 */
	protected static function log( $message ) {
		if ( ! function_exists( 'cp_sync' ) ) {
			return;
		}

		$plugin = cp_sync();

		if ( is_object( $plugin ) && isset( $plugin->logging ) && is_object( $plugin->logging ) ) {
			$plugin->logging->log( $message );
		}
	}
}

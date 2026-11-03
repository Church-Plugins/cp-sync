<?php
/**
 * Which ChMS events and groups are allowed onto the public site.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Visibility and approval checks.
 *
 * Missing keys are treated as "the source did not say", so older payloads
 * keep syncing. A key that is present and fails the check is skipped.
 *
 * @since 1.1.0
 */
class EventVisibility {

	/**
	 * Planning Center Calendar event attributes may sync.
	 *
	 * Requires `visible_in_church_center` to be true when that attribute is
	 * present. Approval blocks sync only for an explicit non-approved value.
	 *
	 * @param array $attributes Event attributes.
	 * @return bool
	 */
	public static function pco_calendar_is_syncable( array $attributes ) {
		if ( array_key_exists( 'visible_in_church_center', $attributes ) && ! self::is_truthy( $attributes['visible_in_church_center'] ) ) {
			return false;
		}

		if ( array_key_exists( 'approval_status', $attributes ) && self::approval_status_blocks_sync( $attributes['approval_status'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Church Community Builder event row may sync.
	 *
	 * The public calendar listing is already limited to approved public
	 * events and usually omits these flags. When a row does carry an
	 * approval, draft, or listed flag, only the public approved values pass.
	 *
	 * @param array $event Normalized event row.
	 * @return bool
	 */
	public static function ccb_is_syncable( array $event ) {
		if ( array_key_exists( 'approval_status', $event ) && self::approval_status_blocks_sync( $event['approval_status'] ) ) {
			return false;
		}

		if ( array_key_exists( 'is_approved', $event ) && ! self::is_truthy( $event['is_approved'] ) ) {
			return false;
		}

		if ( array_key_exists( 'is_draft', $event ) && self::is_truthy( $event['is_draft'] ) ) {
			return false;
		}

		if ( array_key_exists( 'listed', $event ) && ! self::is_truthy( $event['listed'] ) ) {
			return false;
		}

		if ( array_key_exists( 'public', $event ) && ! self::is_truthy( $event['public'] ) ) {
			return false;
		}

		if ( array_key_exists( 'visibility', $event ) ) {
			$visibility = strtolower( trim( (string) $event['visibility'] ) );
			if ( ! in_array( $visibility, array( 'public', 'listed' ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Planning Center group attributes are listed and public.
	 *
	 * Applied only for the "Only Visible in Church Center" groups setting.
	 * Groups that omit both keys are unchanged.
	 *
	 * @param array $attributes Group attributes.
	 * @return bool
	 */
	public static function pco_group_is_listed_public( array $attributes ) {
		if ( array_key_exists( 'listed', $attributes ) && ! self::is_truthy( $attributes['listed'] ) ) {
			return false;
		}

		if ( array_key_exists( 'visibility', $attributes ) ) {
			$visibility = strtolower( trim( (string) $attributes['visibility'] ) );
			if ( 'public' !== $visibility ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Drop Planning Center calendar rows whose Event is hidden or unapproved.
	 *
	 * Rows with no related Event are left in place; the formatter already
	 * skips those.
	 *
	 * @param array $items            Event instance rows.
	 * @param array $relational_data  Included Event records, keyed by id.
	 * @return array
	 */
	public static function filter_pco_calendar_items( array $items, array $relational_data ) {
		return self::partition_pco_calendar_items( $items, $relational_data )['kept'];
	}

	/**
	 * Split calendar rows into those that sync and the instance ids that do not.
	 *
	 * @param array $items           Event instance rows.
	 * @param array $relational_data Included Event records, keyed by id.
	 * @return array{kept: array, skipped_ids: array}
	 */
	public static function partition_pco_calendar_items( array $items, array $relational_data ) {
		$kept        = array();
		$skipped_ids = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$event_id = $item['relationships']['event']['data']['id'] ?? '';
			$event    = $relational_data['Event'][ $event_id ]['attributes'] ?? null;

			if ( ! is_array( $event ) ) {
				$kept[] = $item;
				continue;
			}

			if ( self::pco_calendar_is_syncable( $event ) ) {
				$kept[] = $item;
				continue;
			}

			if ( isset( $item['id'] ) && '' !== (string) $item['id'] ) {
				$skipped_ids[] = (string) $item['id'];
			}
		}

		return array(
			'kept'        => $kept,
			'skipped_ids' => $skipped_ids,
		);
	}

	/**
	 * Drop Planning Center groups that are present and not listed/public.
	 *
	 * @param array $items Group rows.
	 * @return array
	 */
	public static function filter_pco_groups( array $items ) {
		$kept = array();

		foreach ( $items as $item ) {
			$attributes = ( is_array( $item ) && isset( $item['attributes'] ) && is_array( $item['attributes'] ) ) ? $item['attributes'] : array();

			if ( self::pco_group_is_listed_public( $attributes ) ) {
				$kept[] = $item;
			}
		}

		return $kept;
	}

	/**
	 * True for boolean true and the string forms ChMS feeds use.
	 *
	 * @param mixed $value The raw value.
	 * @return bool
	 */
	public static function is_truthy( $value ) {
		if ( true === $value || 1 === $value ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Approved labels used by Church Community Builder and Planning Center.
	 *
	 * @param mixed $status The raw approval status.
	 * @return bool
	 */
	public static function is_approved_label( $status ) {
		$status = strtoupper( trim( (string) $status ) );

		return in_array( $status, array( 'A', 'APPROVED' ), true );
	}

	/**
	 * Whether this approval_status should block sync.
	 *
	 * 'A', 'APPROVED', null, and empty are treated as approved. Only an
	 * explicit non-approved value blocks sync, for example 'P' (pending)
	 * or 'R' (rejected). Which codes Planning Center actually sends is
	 * unverified against live PCO data.
	 *
	 * @param mixed $status Raw approval_status.
	 * @return bool
	 */
	public static function approval_status_blocks_sync( $status ) {
		if ( null === $status ) {
			return false;
		}

		$status = trim( (string) $status );

		if ( '' === $status ) {
			return false;
		}

		return ! self::is_approved_label( $status );
	}
}

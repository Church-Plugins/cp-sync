<?php
/**
 * Post meta keys for CP Sync events and series.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Meta key names shared by the sync and the templates.
 *
 * @since 1.2.0
 */
class Meta {

	const PROVIDER = '_cp_provider';

	const OCCURRENCE_ID = '_cp_occurrence_id';

	const SOURCE_ID = '_cp_source_id';

	/**
	 * Same marker uninstall.php uses to find imported posts.
	 */
	const CHMS_ID = '_chms_id';

	const START_UTC = '_cp_start_utc';

	const END_UTC = '_cp_end_utc';

	const START_LOCAL = '_cp_start_local';

	const END_LOCAL = '_cp_end_local';

	const TIMEZONE = '_cp_timezone';

	const ALL_DAY = '_cp_all_day';

	const LOCATION = '_cp_location';

	const IMAGE_URL = '_cp_image_url';

	const REGISTRATION_URL = '_cp_registration_url';

	/**
	 * WordPress post ID of the series this date belongs to.
	 */
	const SERIES_ID = '_cp_series_id';

	/**
	 * Provider plus ChMS series id, used to upsert the series post.
	 */
	const SERIES_KEY = '_cp_series_key';

	/**
	 * Same key Integrations\Integration uses for the per-post sync lock.
	 */
	const LOCK = '_cp_sync_lock';
}

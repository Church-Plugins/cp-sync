# Change Log

## Local CP-Sync fork modifications (not upstream)
- Pagination now follows the API's own signals (meta.next.offset / links.next)
  instead of the row-count heuristic, which silently truncated crawls when an
  endpoint capped page size server-side (observed: groups capped at 50).
- 429 rate limits are retried with a bounded Retry-After back-off (max 2
  retries per request, waits clamped to 1-30s, total sleep per get() crawl
  capped at 90s), with an optional onRateLimit() callback for logging.
- Added onPageProgress() callback: invoked after each page of a get() crawl so
  callers can log progress (a request killed mid-crawl leaves a trail).

## v1.1.5 (2019-06-28)
- Added delete() method to delete a record
- Added id3 to allow a 3rd numeric parameter on the query string.  This structure is now supported:

	https://api.planningcenteronline.com/module/v2/table/id/associations/id2/association2/id3?parameters

## v1.1.4
- Added method parameterArray() that accepts key-value list of parameters for the API call.
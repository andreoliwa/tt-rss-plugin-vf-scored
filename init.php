<?php
/**
 * Plugin: Scored articles
 *
 * Adds a "Scored articles" virtual feed that shows articles with score >= 1.
 * Works like "Starred" and "Published" - appears in the left sidebar under "Special".
 *
 * This is a user plugin - enable it in Preferences → Plugins.
 */
class Vf_Scored extends Plugin implements IVirtualFeed {

	private $host;

	function about() {
		return array(
			1.0,
			"Adds the 'Scored articles' virtual feed on the left menu, showing articles with score >= 1",
			"andreoliwa"
		);
	}

	function init($host) {
		$this->host = $host;

		// Add virtual feed to the "Special" category
		$host->add_feed(
			Feeds::CATEGORY_SPECIAL,  // -1 = Special category
			__("Scored articles"),    // Feed title
			"grade",                  // Material icon name
			$this                     // Plugin instance (must implement IVirtualFeed)
		);
	}

	function api_version() {
		return 2;
	}

	/**
	 * IVirtualFeed: Get unread count for this virtual feed
	 */
	function get_unread(int $feed_id): int {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT COUNT(int_id) AS unread
			FROM ttrss_user_entries
			WHERE unread = true AND score >= 1 AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		return (int)$row["unread"];
	}

	/**
	 * IVirtualFeed: Get total count for this virtual feed
	 * Only show count when there are unread articles (like "Fresh articles")
	 */
	function get_total(int $feed_id): int {
		// Only show total count if there are unread articles
		$unread = $this->get_unread($feed_id);
		if ($unread == 0) {
			return 0;
		}

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT COUNT(int_id) AS total
			FROM ttrss_user_entries
			WHERE score >= 1 AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		return (int)$row["total"];
	}

	/**
	 * IVirtualFeed: Get headlines for this virtual feed
	 *
	 * Must return array matching Feeds::_get_headlines() format:
	 * [$res, $feed_title, $feed_site_url, $last_error, $last_updated, $search_words, $first_id, $vfeed_enabled, $query_error_override]
	 */
	function get_headlines(int $feed_id, array $options): array {
		$pdo = Db::pdo();

		$limit = (int)($options["limit"] ?? 30);
		$offset = (int)($options["offset"] ?? 0);
		// Fallback to $_SESSION['uid'] if owner_uid is not provided (e.g., from API calls)
		$owner_uid = (int)($options["owner_uid"] ?? $_SESSION['uid']);
		$search = $options["search"] ?? "";
		$view_mode = $options["view_mode"] ?? "";

		// Build WHERE clause
		$where_parts = ["ttrss_user_entries.owner_uid = " . $pdo->quote($owner_uid)];

		// Always filter by score >= 1
		$where_parts[] = "ttrss_user_entries.score >= 1";

		// Handle view_mode (adaptive, unread, etc.)
		if ($view_mode == "adaptive") {
			// In adaptive mode, only show unread if there are any unread scored articles
			// Otherwise show all scored articles (like the main Feeds class does)
			$unread_count = $this->get_unread($feed_id);
			if ($unread_count > 0) {
				$where_parts[] = "ttrss_user_entries.unread = true";
			}
		} else if ($view_mode == "unread") {
			$where_parts[] = "ttrss_user_entries.unread = true";
		} else if ($view_mode == "marked") {
			$where_parts[] = "ttrss_user_entries.marked = true";
		}

		// Handle search
		$search_words = [];
		if (!empty($search)) {
			$where_parts[] = "LOWER(ttrss_entries.title) LIKE LOWER(" . $pdo->quote("%$search%") . ")";
			$search_words = explode(" ", $search);
		}

		$where_clause = implode(" AND ", $where_parts);

		// Build the full query - must select all columns that Feeds::_format_headlines_list expects
		// This matches the SELECT from Feeds::_get_headlines()
		$sql = "SELECT
				ttrss_entries.id AS id,
				ttrss_entries.date_entered,
				ttrss_entries.guid,
				ttrss_entries.title,
				ttrss_entries.updated,
				ttrss_user_entries.label_cache,
				ttrss_user_entries.tag_cache,
				ttrss_feeds.always_display_enclosures,
				ttrss_feeds.site_url,
				ttrss_user_entries.note,
				ttrss_entries.num_comments,
				ttrss_entries.comments,
				ttrss_entries.lang,
				ttrss_user_entries.int_id,
				ttrss_user_entries.uuid,
				ttrss_user_entries.unread,
				ttrss_user_entries.feed_id,
				ttrss_user_entries.marked,
				ttrss_user_entries.published,
				ttrss_user_entries.last_read,
				ttrss_user_entries.last_marked,
				ttrss_user_entries.last_published,
				ttrss_user_entries.score,
				ttrss_entries.link,
				ttrss_entries.content,
				ttrss_entries.author,
				ttrss_feeds.title AS feed_title,
				ttrss_feeds.hide_images,
				(SELECT count(label_id) FROM ttrss_user_labels2 WHERE article_id = ttrss_entries.id) AS num_labels,
				(SELECT count(id) FROM ttrss_enclosures WHERE post_id = ttrss_entries.id) AS num_enclosures
			FROM ttrss_user_entries
			LEFT JOIN ttrss_entries ON (ttrss_entries.id = ttrss_user_entries.ref_id)
			LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = ttrss_user_entries.feed_id)
			WHERE $where_clause
			ORDER BY ttrss_user_entries.score DESC, ttrss_entries.updated DESC
			LIMIT $limit OFFSET $offset";

		$res = $pdo->query($sql);

		// Return format matching Feeds::_get_headlines()
		return [
			$res,                    // PDO result
			"Scored articles",       // feed title
			"",                      // feed site URL
			"",                      // last error
			"",                      // last updated
			$search_words,           // search words array
			0,                       // first_id
			true,                    // vfeed enabled (show feed source column)
			""                       // query error override
		];
	}

}


<?php
/**
 * Real-WordPress smoke test for Newsletter Publisher.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file.\n" );
	exit( 1 );
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$GLOBALS['failures'] = 0;
$GLOBALS['checks']   = 0;

function t_check( string $label, bool $ok, string $detail = '' ): void {
	++$GLOBALS['checks'];
	if ( ! $ok ) {
		++$GLOBALS['failures'];
		echo "FAIL {$label}" . ( '' !== $detail ? " :: {$detail}" : '' ) . "\n";
	} else {
		echo "PASS {$label}\n";
	}
}

function t_contains( string $label, string $haystack, string $needle ): void {
	t_check( $label, false !== strpos( $haystack, $needle ), 'missing: ' . $needle );
}

function t_not_contains( string $label, string $haystack, string $needle ): void {
	t_check( $label, false === strpos( $haystack, $needle ), 'unexpected: ' . $needle );
}

wp_set_current_user( 1 );

// ---------------------------------------------------------------- reset plugin content.
foreach ( array( \NLWP\Post_Types::EVENT, \NLWP\Post_Types::ISSUE ) as $cleanup_type ) {
	$cleanup_ids = get_posts(
		array(
			'post_type'   => $cleanup_type,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $cleanup_ids as $cleanup_id ) {
		wp_delete_post( (int) $cleanup_id, true );
	}
}

// ---------------------------------------------------------------- settings.
$settings = \NLWP\Settings::defaults();
$settings['languages'] = array(
	array( 'code' => 'en', 'label' => 'English', 'short' => 'EN', 'locale' => 'en_US', 'dir' => 'ltr', 'enabled' => true, 'date_format' => '' ),
	array( 'code' => 'fi', 'label' => 'Suomi', 'short' => 'FI', 'locale' => 'fi_FI', 'dir' => 'ltr', 'enabled' => true, 'date_format' => 'j.n.Y' ),
);
$settings['default_language'] = 'en';
$settings['display_mode']     = 'stacked';
$settings['field_modes']      = array(
	'category_labels'    => 'inherit',
	'event_titles'       => 'inherit',
	'event_descriptions' => 'inherit',
	'issue_title'        => 'inherit',
	'issue_intro'        => 'inherit',
	'ui_strings'         => 'inherit',
);
$settings['stacked'] = array(
	'show_labels'           => true,
	'label_format'          => 'code',
	'title_layout'          => 'inline',
	'separator'             => ' / ',
	'description_separator' => 'line',
);
$settings['hide_untranslated_events'] = false;
$settings['title_source']    = 'issue';
$settings['show_intro']      = true;
$settings['show_issue_date'] = true;
$settings['date_format']     = 'j.n.Y';
$settings['show_numbers']    = true;
$settings['numbering']       = 'continuous';
$settings['accordion']       = true;
$settings['show_categories'] = true;
$settings['show_dates']      = true;
$settings['show_end_dates']  = true;
$settings['show_attachments'] = true;
$settings['attachment_style'] = 'button';
$settings['strings']['fixed_title']         = array( 'en' => 'Bulletin', 'fi' => 'Tiedote' );
$settings['strings']['issue_date_label']    = array( 'en' => 'Date', 'fi' => 'Päivä' );
$settings['strings']['attachment_label']    = array( 'en' => 'File', 'fi' => 'Tiedosto' );
$settings['strings']['uncategorized_label'] = array( 'en' => 'Other', 'fi' => 'Muut' );
$settings['strings']['empty_text']          = array( 'en' => '<p>No events.</p>', 'fi' => '<p>Ei tapahtumia.</p>' );
update_option( \NLWP\Settings::OPTION, $settings );

$settings = \NLWP\Settings::get_settings();
t_check( 'settings languages normalize', 2 === count( $settings['languages'] ) && 'en' === $settings['default_language'] );

// ---------------------------------------------------------------- categories.
$guild = get_term_by( 'slug', 'guild-events', \NLWP\Taxonomy::TAXONOMY );
$music = term_exists( 'music', \NLWP\Taxonomy::TAXONOMY );
if ( ! $music ) {
	$music = wp_insert_term( 'Music', \NLWP\Taxonomy::TAXONOMY, array( 'slug' => 'music' ) );
}
$guild_id = $guild ? (int) $guild->term_id : 0;
$music_id = is_wp_error( $music ) ? 0 : (int) ( is_array( $music ) ? $music['term_id'] : $music );
update_term_meta( $guild_id, '_nlwp_label_translations', wp_slash( array( 'fi' => 'Kilta' ) ) );
update_term_meta( $music_id, '_nlwp_label_translations', wp_slash( array( 'fi' => 'Musiikki' ) ) );
update_term_meta( $guild_id, '_nlwp_order', 1 );
update_term_meta( $music_id, '_nlwp_order', 2 );
t_check( 'default categories exist', $guild_id > 0 && $music_id > 0 );
t_check( 'default terms enabled', '1' === (string) get_term_meta( $guild_id, '_nlwp_enabled', true ) );
t_check( 'default term label translation', 'Kilta' === \NLWP\Translations::get_term_label( $guild_id, 'fi' ) );

// ---------------------------------------------------------------- attachment.
$uploads = wp_upload_dir();
$file    = trailingslashit( $uploads['path'] ) . 'nlwp-test-file.txt';
file_put_contents( $file, 'test' );
$attachment_id = wp_insert_attachment(
	array(
		'post_title'     => 'Programme',
		'post_mime_type' => 'text/plain',
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
	),
	$file
);
update_post_meta( $attachment_id, '_wp_attached_file', ltrim( str_replace( $uploads['basedir'], '', $file ), '/' ) );
t_check( 'attachment created', $attachment_id > 0 && false !== wp_get_attachment_url( $attachment_id ) );

// ---------------------------------------------------------------- events.
$event = static function ( string $title, string $content, string $start, string $end, int $term_id, array $translations = array(), int $attachment = 0 ) {
	$id = wp_insert_post(
		array(
			'post_type'    => \NLWP\Post_Types::EVENT,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	wp_set_object_terms( $id, array( $term_id ), \NLWP\Taxonomy::TAXONOMY );
	$_POST = array(
		\NLWP\Admin_Events::NONCE => wp_create_nonce( \NLWP\Admin_Events::NONCE ),
		'nlwp_start_date'         => $start,
		'nlwp_end_date'           => $end,
		'nlwp_show_date'          => '1',
		'nlwp_attachment_id'      => (string) $attachment,
	);
	\NLWP\Admin_Events::save( $id, get_post( $id ) );

	$_POST = array(
		\NLWP\Admin_Translations::NONCE => wp_create_nonce( \NLWP\Admin_Translations::NONCE ),
		'nlwp_translations'             => $translations,
	);
	\NLWP\Admin_Translations::save( $id, get_post( $id ) );

	return $id;
};

$e1 = $event(
	'Autumn meeting',
	'<p>Meet the guild. Visit https://example.test for details.</p>',
	'2026-11-01',
	'2026-11-03',
	$guild_id,
	array( 'fi' => array( 'title' => 'Syyskokous', 'description' => '<p>Tapaa kilta.</p>' ) ),
	$attachment_id
);
$e2 = $event( 'Live concert', '<p>Music all night.</p>', '2026-11-10', '', $music_id );
$e3 = $event( 'Winter trip', '<p>Too late for this issue.</p>', '2026-12-15', '', $music_id, array( 'fi' => array( 'title' => 'Talviretki', 'description' => '<p>Myöhäinen.</p>' ) ) );
t_check( 'events created', $e1 > 0 && $e2 > 0 && $e3 > 0 );
t_check( 'event dates saved', '2026-11-01' === get_post_meta( $e1, '_nlwp_start_date', true ) && '2026-11-03' === get_post_meta( $e1, '_nlwp_end_date', true ) );
t_check( 'event attachment saved', $attachment_id === (int) get_post_meta( $e1, '_nlwp_attachment_id', true ) );
t_check( 'event translations saved', 'Syyskokous' === ( \NLWP\Translations::get_post_translations( $e1 )['fi']['title'] ?? '' ) );

// ---------------------------------------------------------------- issue.
$issue_id = wp_insert_post(
	array(
		'post_type'    => \NLWP\Post_Types::ISSUE,
		'post_status'  => 'publish',
		'post_title'   => 'November issue',
		'post_content' => '<p>Welcome to November.</p>',
	),
	true
);
t_check( 'issue created', $issue_id > 0 && ! is_wp_error( $issue_id ) );

$_POST = array(
	\NLWP\Admin_Issues::NONCE       => wp_create_nonce( \NLWP\Admin_Issues::NONCE ),
	'nlwp_issue_date'               => '2026-11-13',
	'nlwp_period_start'             => '2026-11-01',
	'nlwp_period_end'               => '2026-11-30',
	'nlwp_selection_mode'           => 'auto',
);
\NLWP\Admin_Issues::save( $issue_id, get_post( $issue_id ) );

$_POST = array(
	\NLWP\Admin_Translations::NONCE => wp_create_nonce( \NLWP\Admin_Translations::NONCE ),
	'nlwp_translations'             => array( 'fi' => array( 'title' => 'Marraskuun numero', 'intro' => '<p>Tervetuloa marraskuuhun.</p>' ) ),
);
\NLWP\Admin_Translations::save( $issue_id, get_post( $issue_id ) );

$ids = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'auto selection includes overlapping events', array( $e1, $e2 ) === $ids, wp_json_encode( $ids ) );
t_check( 'auto selection excludes out-of-period event', ! in_array( $e3, $ids, true ) );
t_check( 'auto selection ordered by category', array_search( $e1, $ids, true ) < array_search( $e2, $ids, true ), wp_json_encode( $ids ) );
t_check( 'issue translations saved', 'Marraskuun numero' === ( \NLWP\Translations::get_post_translations( $issue_id )['fi']['title'] ?? '' ) );
t_check( 'issue synced timestamp saved', '' !== (string) get_post_meta( $issue_id, '_nlwp_events_synced_at', true ) );

// ---------------------------------------------------------------- stacked render.
$stacked = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" display_mode="stacked"]' );
t_check( 'stacked render non-empty', strlen( $stacked ) > 500 );
t_contains( 'stacked wrapper mode', $stacked, 'nlwp-mode-stacked' );
t_contains( 'stacked issue title en', $stacked, 'November issue' );
t_contains( 'stacked issue title fi', $stacked, 'Marraskuun numero' );
t_contains( 'stacked intro en', $stacked, 'Welcome to November.' );
t_contains( 'stacked intro fi', $stacked, 'Tervetuloa marraskuuhun.' );
t_contains( 'stacked category en', $stacked, 'Guild events' );
t_contains( 'stacked category fi', $stacked, 'Kilta' );
t_contains( 'stacked event title en', $stacked, 'Autumn meeting' );
t_contains( 'stacked event title fi', $stacked, 'Syyskokous' );
t_contains( 'stacked event description fi', $stacked, 'Tapaa kilta.' );
t_contains( 'stacked language labels', $stacked, 'nlwp-lang-label' );
t_contains( 'stacked description separator', $stacked, 'nlwp-lang-separator' );
t_contains( 'stacked date en', $stacked, '1.11.2026' );
t_contains( 'stacked date end', $stacked, '3.11.2026' );
t_contains( 'attachment label en', $stacked, 'File / Tiedosto:' );
t_contains( 'attachment label fi', $stacked, 'Tiedosto:' );
t_contains( 'attachment file name', $stacked, 'Programme' );
t_contains( 'issue date label', $stacked, 'Date' );
t_contains( 'issue date value', $stacked, '13.11.2026' );
t_contains( 'accordion markup', $stacked, '<details class="nlwp-event' );
t_contains( 'continuous numbering', $stacked, '>2.</span>' );
t_contains( 'autolink applied', $stacked, '<a href="https://example.test"' );

// ---------------------------------------------------------------- site render, Finnish.
$site_fi = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" display_mode="site" language="fi"]' );
t_contains( 'site fi wrapper', $site_fi, 'nlwp-mode-site' );
t_contains( 'site fi title', $site_fi, 'Marraskuun numero' );
t_contains( 'site fi intro', $site_fi, 'Tervetuloa marraskuuhun.' );
t_contains( 'site fi category', $site_fi, 'Kilta' );
t_contains( 'site fi translated event', $site_fi, 'Syyskokous' );
t_not_contains( 'site fi omits default title', $site_fi, 'November issue' );
t_contains( 'site fi falls back for untranslated event', $site_fi, 'Live concert' );
t_contains( 'site fi issue date', $site_fi, '13.11.2026' );

// Hide untranslated events in site mode.
$hide = $settings;
$hide['hide_untranslated_events'] = true;
update_option( \NLWP\Settings::OPTION, $hide );
$hidden = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" display_mode="site" language="fi"]' );
t_not_contains( 'hide untranslated removes event', $hidden, 'Live concert' );
t_contains( 'hide untranslated keeps translated event', $hidden, 'Syyskokous' );
update_option( \NLWP\Settings::OPTION, $settings );

// ---------------------------------------------------------------- manual selection.
$_POST = array(
	\NLWP\Admin_Issues::NONCE => wp_create_nonce( \NLWP\Admin_Issues::NONCE ),
	'nlwp_issue_date'         => '2026-11-13',
	'nlwp_period_start'       => '2026-11-01',
	'nlwp_period_end'         => '2026-11-30',
	'nlwp_selection_mode'     => 'manual',
	'nlwp_event_ids'          => array( (string) $e2 ),
);
\NLWP\Admin_Issues::save( $issue_id, get_post( $issue_id ) );
$manual = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'manual selection saved', array( $e2 ) === $manual, wp_json_encode( $manual ) );
$manual_html = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '"]' );
t_not_contains( 'manual selection excludes unselected event', $manual_html, 'Autumn meeting' );
t_contains( 'manual selection includes selected event', $manual_html, 'Live concert' );

// Back to auto for resync tests.
$_POST = array(
	\NLWP\Admin_Issues::NONCE => wp_create_nonce( \NLWP\Admin_Issues::NONCE ),
	'nlwp_issue_date'         => '2026-11-13',
	'nlwp_period_start'       => '2026-11-01',
	'nlwp_period_end'         => '2026-11-30',
	'nlwp_selection_mode'     => 'auto',
);
\NLWP\Admin_Issues::save( $issue_id, get_post( $issue_id ) );

// Event save resync: move e3 into the period through a real post update.
$_POST = array(
	\NLWP\Admin_Events::NONCE => wp_create_nonce( \NLWP\Admin_Events::NONCE ),
	'nlwp_start_date'         => '2026-11-20',
	'nlwp_end_date'           => '',
	'nlwp_show_date'          => '1',
	'nlwp_attachment_id'      => '0',
);
wp_update_post( array( 'ID' => $e3, 'post_title' => 'Winter trip' ) );
$resynced = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'event save resyncs auto issue', in_array( $e3, $resynced, true ), wp_json_encode( $resynced ) );

// Trash resync: trashed event disappears.
wp_trash_post( $e3 );
$after_trash = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'trash resync removes event', ! in_array( $e3, $after_trash, true ), wp_json_encode( $after_trash ) );
wp_untrash_post( $e3 );
$after_untrash = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'untrash keeps draft event out', ! in_array( $e3, $after_untrash, true ), wp_json_encode( $after_untrash ) );
wp_update_post( array( 'ID' => $e3, 'post_status' => 'publish' ) );
$after_republish = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'republish resync restores event', in_array( $e3, $after_republish, true ), wp_json_encode( $after_republish ) );

// Delete resync: deleted event disappears.
wp_delete_post( $e3, true );
$after_delete = \NLWP\Issue_Selection::sort_event_ids( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
t_check( 'delete resync removes event', ! in_array( $e3, $after_delete, true ), wp_json_encode( $after_delete ) );

// ---------------------------------------------------------------- block.
$block = do_blocks( '<!-- wp:newsletter-wp-plugin/newsletter {"issueId":' . $issue_id . ',"displayMode":"stacked"} /-->' );
t_check( 'block renders newsletter', false !== strpos( $block, 'class="nlwp ' ) && false !== strpos( $block, 'Marraskuun numero' ) );
$aligned = do_blocks( '<!-- wp:newsletter-wp-plugin/newsletter {"issueId":' . $issue_id . ',"align":"wide"} /-->' );
t_contains( 'block alignment wrapper', $aligned, 'alignwide' );
t_contains( 'block wrapper class', $aligned, 'wp-block-newsletter-wp-plugin-newsletter' );

// ---------------------------------------------------------------- shortcode flags.
$no_title = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" show_title="0" show_intro="0"]' );
t_not_contains( 'show_title=0 hides title', $no_title, 'November issue' );
t_not_contains( 'show_intro=0 hides intro', $no_title, 'Welcome to November.' );

// ---------------------------------------------------------------- REST exposure.
$server = rest_get_server();
$routes = array( '/wp/v2/nlwp_issue', '/wp/v2/nlwp_event', '/wp/v2/nlwp_category' );
foreach ( $routes as $route ) {
	$request  = new WP_REST_Request( 'GET', $route );
	$response = $server->dispatch( $request );
	t_check( 'REST route not public: ' . $route, in_array( $response->get_status(), array( 404, 401, 403 ), true ), 'status ' . $response->get_status() );
}

// ---------------------------------------------------------------- template fallbacks.
$empty_issue = wp_insert_post(
	array(
		'post_type'    => \NLWP\Post_Types::ISSUE,
		'post_status'  => 'publish',
		'post_title'   => 'Empty issue',
		'post_content' => '',
	)
);
$_POST = array(
	\NLWP\Admin_Issues::NONCE => wp_create_nonce( \NLWP\Admin_Issues::NONCE ),
	'nlwp_issue_date'         => '2026-11-13',
	'nlwp_period_start'       => '2027-01-01',
	'nlwp_period_end'         => '2027-01-31',
	'nlwp_selection_mode'     => 'auto',
);
\NLWP\Admin_Issues::save( $empty_issue, get_post( $empty_issue ) );
$empty_html = do_shortcode( '[nlwp_newsletter issue_id="' . $empty_issue . '" display_mode="site"]' );
t_contains( 'empty text fallback', $empty_html, 'No events.' );
t_contains( 'empty text rich html', $empty_html, '<p>No events.</p>' );

// Uncategorized event.
$e4 = wp_insert_post(
	array(
		'post_type'    => \NLWP\Post_Types::EVENT,
		'post_status'  => 'publish',
		'post_title'   => 'Uncategorised event',
		'post_content' => '<p>No category.</p>',
	)
);
update_post_meta( $e4, '_nlwp_start_date', '2027-01-10' );
$uncat = do_shortcode( '[nlwp_newsletter issue_id="' . $empty_issue . '" display_mode="site"]' );
t_contains( 'uncategorized label', $uncat, 'Other' );
t_contains( 'uncategorized event rendered', $uncat, 'Uncategorised event' );

// Fixed title source.
$fixed = $settings;
$fixed['title_source'] = 'fixed';
update_option( \NLWP\Settings::OPTION, $fixed );
$fixed_html = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" display_mode="site" language="en"]' );
t_contains( 'fixed title en', $fixed_html, 'Bulletin' );
$fixed_fi = do_shortcode( '[nlwp_newsletter issue_id="' . $issue_id . '" display_mode="site" language="fi"]' );
t_contains( 'fixed title fi', $fixed_fi, 'Tiedote' );
update_option( \NLWP\Settings::OPTION, $settings );

// ---------------------------------------------------------------- summary.
echo "\n{$GLOBALS['checks']} checks, {$GLOBALS['failures']} failures\n";
if ( $GLOBALS['failures'] > 0 ) {
	exit( 1 );
}

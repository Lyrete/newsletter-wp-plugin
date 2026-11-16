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

// ---------------------------------------------------------------- email settings and rendering.
$email_settings = $settings;
$email_settings['email']['from_name']  = 'Newsletter Test';
$email_settings['email']['from_email'] = 'newsletter@example.test';
$email_settings['email']['reply_to']   = 'reply@example.test';
$email_settings['email']['format']     = 'plain';
$email_settings['email']['formatting'] = array(
	'languages'                    => array( 'en', 'fi' ),
	'primary_language'             => 'en',
	'title_layout'                 => 'block',
	'title_separator'              => ' | ',
	'title_labels'                 => true,
	'label_format'                 => 'code',
	'description_separator'        => 'custom',
	'description_custom_separator' => '---',
	'description_labels'           => true,
);
$email_settings['strings']['email_header'] = array( 'en' => 'Header EN', 'fi' => 'Header FI' );
$email_settings['strings']['email_footer'] = array( 'en' => 'Footer EN', 'fi' => 'Footer FI' );
update_option( \NLWP\Settings::OPTION, $email_settings );
$email_settings = \NLWP\Settings::get_settings();

t_check( 'email settings defaults normalized', 'plain' === $email_settings['email']['format'] && 'Newsletter Test' === $email_settings['email']['from_name'] );
t_check( 'email header string saved', 'Header EN' === ( $email_settings['strings']['email_header']['en'] ?? '' ) );

delete_post_meta( $issue_id, \NLWP\Email_Renderer::META_FORMATTING );
delete_post_meta( $issue_id, '_nlwp_email_subject' );

$formatting = \NLWP\Email_Renderer::formatting( $issue_id, $email_settings );
t_check( 'email formatting inherits settings', array( 'en', 'fi' ) === $formatting['languages'] && 'en' === $formatting['primary_language'] );
t_check( 'email formatting custom separator', 'custom' === $formatting['description_separator'] && '---' === $formatting['description_custom_separator'] );

$fallback_formatting = \NLWP\Email_Renderer::sanitize_formatting( array( 'primary_language' => 'fi', 'languages' => array() ), $email_settings );
t_check( 'email empty languages fall back to primary', array( 'fi' ) === $fallback_formatting['languages'] && 'fi' === $fallback_formatting['primary_language'] );

$plain = \NLWP\Email_Renderer::message( $issue_id, 'plain', $email_settings );
$html  = \NLWP\Email_Renderer::message( $issue_id, 'html', $email_settings );

t_check( 'email subject uses primary issue title', 'November issue' === $plain['subject'], $plain['subject'] );
t_check( 'email plain format', 'plain' === $plain['format'] && 'html' === $html['format'] );
t_contains( 'email header primary language', $plain['body'], 'Header EN' );
t_contains( 'email footer primary language', $plain['body'], 'Footer EN' );
t_not_contains( 'email header omits secondary language', $plain['body'], 'Header FI' );
t_contains( 'email title label en', $plain['body'], 'en: November issue' );
t_contains( 'email title label fi', $plain['body'], 'fi: Marraskuun numero' );
t_contains( 'email date label and value', $plain['body'], 'Date: 13.11.2026' );
t_contains( 'email category labels', $plain['body'], 'en: Guild events' );
t_contains( 'email category label fi', $plain['body'], 'fi: Kilta' );
t_contains( 'email event title en', $plain['body'], 'en: Autumn meeting' );
t_contains( 'email event title fi', $plain['body'], 'fi: Syyskokous' );
t_contains( 'email event description fi', $plain['body'], 'fi: Tapaa kilta.' );
t_contains( 'email custom description separator', $plain['body'], '---' );
t_contains( 'email autolink text preserved', $plain['body'], 'https://example.test' );
t_not_contains( 'email plain has no paragraph tags', $plain['body'], '<p>' );
t_contains( 'email html has heading', $html['body'], '<h1>' );
t_contains( 'email html has event heading', $html['body'], '<h3>' );
t_not_contains( 'email html has no tables', $html['body'], '<table' );
t_not_contains( 'email html has no images', $html['body'], '<img' );
t_not_contains( 'email html has no scripts', $html['body'], '<script' );
t_check( 'email export matches message', \NLWP\Email_Renderer::export( $issue_id, 'plain', $email_settings ) === $plain['body'] );

update_post_meta( $issue_id, '_nlwp_email_subject', 'Custom subject' );
t_check( 'email subject meta override', 'Custom subject' === \NLWP\Email_Renderer::subject( $issue_id, $email_settings ) );
delete_post_meta( $issue_id, '_nlwp_email_subject' );

// Per-issue formatting override.
update_post_meta(
	$issue_id,
	\NLWP\Email_Renderer::META_FORMATTING,
	wp_slash(
		array(
			'languages'        => array( 'fi' ),
			'primary_language' => 'fi',
		)
	)
);
$fi_formatting = \NLWP\Email_Renderer::formatting( $issue_id, $email_settings );
t_check( 'per-issue formatting overrides languages', array( 'fi' ) === $fi_formatting['languages'] && 'fi' === $fi_formatting['primary_language'] );
$fi_plain = \NLWP\Email_Renderer::message( $issue_id, 'plain', $email_settings );
t_check( 'per-issue primary language drives subject', 'Marraskuun numero' === $fi_plain['subject'], $fi_plain['subject'] );
t_contains( 'per-issue language keeps translated event', $fi_plain['body'], 'Syyskokous' );
t_contains( 'per-issue language falls back for untranslated event', $fi_plain['body'], 'Live concert' );
delete_post_meta( $issue_id, \NLWP\Email_Renderer::META_FORMATTING );

// ---------------------------------------------------------------- email sender.
t_check( 'parse recipients', array( 'a@example.test', 'b@example.test', 'c@example.test' ) === \NLWP\Email_Sender::parse_recipients( "a@example.test, b@example.test; c@example.test bad" ) );
t_check( 'sender from helpers', 'newsletter@example.test' === \NLWP\Email_Sender::from_email( $email_settings ) && 'Newsletter Test' === \NLWP\Email_Sender::from_name( $email_settings ) );
t_check( 'sender reply-to helper', 'reply@example.test' === \NLWP\Email_Sender::reply_to( $email_settings ) );
t_check( 'sender neutral to uses from email', 'newsletter@example.test' === \NLWP\Email_Sender::neutral_to( $email_settings ) );

update_post_meta( $issue_id, '_nlwp_email_recipients', "one@example.test\ntwo@example.test" );
t_check( 'saved recipients parsed', array( 'one@example.test', 'two@example.test' ) === \NLWP\Email_Sender::recipients( $issue_id ) );

$GLOBALS['nlwp_mail'] = array();
$capture_mail         = static function ( $return, $atts ) {
	$GLOBALS['nlwp_mail'][] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $capture_mail, 10, 2 );

\NLWP\Email_Sender::clear_lock( $issue_id );
$send_result = \NLWP\Email_Sender::send( $issue_id );
t_check( 'sender reports success', ! empty( $send_result['success'] ), (string) ( $send_result['message'] ?? '' ) );
t_check( 'wp_mail called once', 1 === count( $GLOBALS['nlwp_mail'] ) );
t_check( 'last send meta saved', '' !== (string) get_post_meta( $issue_id, '_nlwp_email_last_send', true ) );

$captured = $GLOBALS['nlwp_mail'][0] ?? array();
$headers  = isset( $captured['headers'] ) && is_array( $captured['headers'] ) ? implode( "\n", $captured['headers'] ) : (string) ( $captured['headers'] ?? '' );
t_check( 'sender uses neutral to', 'newsletter@example.test' === ( $captured['to'] ?? '' ), (string) ( $captured['to'] ?? '' ) );
t_contains( 'sender subject', (string) ( $captured['subject'] ?? '' ), 'November issue' );
t_contains( 'sender bcc recipients', $headers, 'Bcc: one@example.test, two@example.test' );
t_contains( 'sender content type plain', $headers, 'Content-Type: text/plain' );
t_contains( 'sender reply-to header', $headers, 'Reply-To: reply@example.test' );
t_contains( 'sender body content', (string) ( $captured['message'] ?? '' ), 'Autumn meeting' );

$locked_result = \NLWP\Email_Sender::send( $issue_id );
t_check( 'duplicate send lock', empty( $locked_result['success'] ) && 1 === count( $GLOBALS['nlwp_mail'] ) );
\NLWP\Email_Sender::clear_lock( $issue_id );

// HTML sending format.
$html_settings = $email_settings;
$html_settings['email']['format'] = 'html';
update_option( \NLWP\Settings::OPTION, $html_settings );
$GLOBALS['nlwp_mail'] = array();
\NLWP\Email_Sender::clear_lock( $issue_id );
$html_send_result = \NLWP\Email_Sender::send( $issue_id );
$html_captured    = $GLOBALS['nlwp_mail'][0] ?? array();
$html_headers     = isset( $html_captured['headers'] ) && is_array( $html_captured['headers'] ) ? implode( "\n", $html_captured['headers'] ) : (string) ( $html_captured['headers'] ?? '' );
t_check( 'sender html success', ! empty( $html_send_result['success'] ), (string) ( $html_send_result['message'] ?? '' ) );
t_contains( 'sender content type html', $html_headers, 'Content-Type: text/html' );
t_contains( 'sender html body', (string) ( $html_captured['message'] ?? '' ), '<h1>' );
update_option( \NLWP\Settings::OPTION, $email_settings );
\NLWP\Email_Sender::clear_lock( $issue_id );

$GLOBALS['nlwp_mail'] = array();
$test_result = \NLWP\Email_Sender::send_test( $issue_id, 'test@example.test' );
t_check( 'test send reports success', ! empty( $test_result['success'] ), (string) ( $test_result['message'] ?? '' ) );
$test_captured = $GLOBALS['nlwp_mail'][0] ?? array();
t_check( 'test send targets one address', 'test@example.test' === ( $test_captured['to'] ?? '' ) );
t_contains( 'test subject prefix', (string) ( $test_captured['subject'] ?? '' ), '[Test] November issue' );
t_check( 'last test meta saved', '' !== (string) get_post_meta( $issue_id, '_nlwp_email_last_test', true ) );

$bad_test = \NLWP\Email_Sender::send_test( $issue_id, 'not-an-email' );
t_check( 'test send rejects invalid address', empty( $bad_test['success'] ) );

remove_filter( 'pre_wp_mail', $capture_mail, 10 );
\NLWP\Email_Sender::clear_lock( $issue_id );

// ---------------------------------------------------------------- email admin screen.
$_GET['page']     = \NLWP\Admin_Email::PAGE;
$_GET['issue_id'] = $issue_id;
ob_start();
\NLWP\Admin_Email::render();
$admin_email_html = (string) ob_get_clean();

t_contains( 'email admin compose form', $admin_email_html, 'nlwp-email-form' );
t_contains( 'email admin send button', $admin_email_html, 'nlwp-send-email' );
t_contains( 'email admin recipients field', $admin_email_html, 'one@example.test' );
t_contains( 'email admin plain preview', $admin_email_html, 'nlwp-preview-plain' );
t_contains( 'email admin html preview', $admin_email_html, 'nlwp-preview-html' );
t_contains( 'email admin copy button', $admin_email_html, 'nlwp-copy' );
t_contains( 'email admin download button', $admin_email_html, 'nlwp-download' );
t_contains( 'email admin smtp notice', $admin_email_html, 'wp_mail()' );
t_contains( 'email admin compose url', \NLWP\Admin_Email::url( $issue_id ), 'page=' . \NLWP\Admin_Email::PAGE );
unset( $_GET['issue_id'], $_GET['page'] );

// ---------------------------------------------------------------- summary.
echo "\n{$GLOBALS['checks']} checks, {$GLOBALS['failures']} failures\n";
if ( $GLOBALS['failures'] > 0 ) {
	exit( 1 );
}

<?php
/**
 * @package Studium_Ausland
 */

define( 'FBK_INC_DIR', get_stylesheet_directory() . '/inc' );
define( 'FBK_CACHE_DIR', get_stylesheet_directory() . '/cache' );

/*
 * Detect front-end AJAX request
 */
if ( ! defined( 'FBK_AJAX' ) ) {
	if ( false === strpos( $_SERVER['REQUEST_URI'], '/admin-ajax.php' ) || false === strpos( $_SERVER['QUERY_STRING'], 'action=fbk' ) )
		define( 'FBK_AJAX', false );
	else
		define( 'FBK_AJAX', true );
}

/*
 * Set profiler
 */
if ( ! defined( 'FBK_PROFILING' ) )
	define( 'FBK_PROFILING', false );
elseif ( FBK_PROFILING )
	require( FBK_INC_DIR . '/profiler.php' );

/*
 * Include general theme files
 */
require( FBK_INC_DIR . '/class-fbk.php' );
require( FBK_INC_DIR . '/functions.php' );
require( FBK_INC_DIR . '/query.php' );
require( FBK_INC_DIR . '/templatetags.php' );
require( FBK_INC_DIR . '/navmenu.php' );
require( FBK_INC_DIR . '/rewrite.php' );
require( FBK_INC_DIR . '/category-meta.php' );
require_once( FBK_INC_DIR . '/cache.php' ); // Once, because ajax-ap.php might already have included it
require( FBK_INC_DIR . '/quotes.php' );

/*
 * Include front-end AJAX handler
 */
if ( FBK_AJAX ) {
	require( FBK_INC_DIR . '/ajax.php' );

/*
 * Include theme files for the back-end
 */
} elseif ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
	require( FBK_INC_DIR . '/theme-options.php' );
	require( FBK_INC_DIR . '/media.php' );
	require( FBK_INC_DIR . '/offers.php' );
	require( FBK_INC_DIR . '/school-connect.php' );
	require( FBK_INC_DIR . '/school-object-share.php' );
	require( FBK_INC_DIR . '/link-shortcode.php' );
	require( FBK_INC_DIR . '/customfields.php' );
	require( FBK_INC_DIR . '/quote-admin.php' );
}

require( FBK_INC_DIR . '/version.php' );

define( 'FBK_CF_FEATURE', 'fbk-cf' );

define( 'FBK_DESC_SLUG', 'schule' );
define( 'FBK_ACCOMMODATION_SLUG', 'unterkunft' );
define( 'FBK_OTHER_SLUG', 'diverses' );
define( 'FBK_GALLERY_SLUG', 'bilder' );
define( 'FBK_DEFAULT_SLICE', FBK_DESC_SLUG );

define( 'FBK_COURSE_PRICE_COLS', 3 );

define( 'FBK_IMAGESIZE_SIDEBAR', '164x80' );
define( 'FBK_IMAGESIZE_HEADER', '574x150' );
define( 'FBK_IMAGESIZE_TEASER', 'thumbnail' );

define( 'FBK_SIDEBAR_DEFAULT', 0 );
define( 'FBK_SIDEBAR_INDEX', 1 );

define( 'FBK_LANG_ORDER_MENU', 'lang_order' );

if ( ! defined('FBK_COLORS_CSS_FILE') )
	define( 'FBK_COLORS_CSS_FILE', 'colors.css' );

$GLOBALS['fbk'] = new FBK;
$GLOBALS['fbk_cache'] = new FBK_Cache;

$GLOBALS['fbk_cf_slices'] = array(
	FBK_DESC_SLUG,
	FBK_ACCOMMODATION_SLUG,
	FBK_OTHER_SLUG,
	FBK_GALLERY_SLUG
);

$GLOBALS['fbk_catmeta_defaults'] = array(
	'menu_heading' => '{category}',
	'menu_level_0' => '{category} {lang}',
	'menu_level_1' => '{category} in {country}',
	'menu_level_2' => '{category} in {city}',
	'menu_level_3' => '',

	'category_title' => '{category_shortdesc} | {sitename}',
	'category_desc' => '{sitename}: {category} in {countries_and}',
	'category_h1' => '{category}',
	'category_h2' => 'Anbieter {category_shortdesc} &ndash; unsere Auswahl',
	'category_langbox_heading' => '{lang}',
	'category_langbox_entry_text' => '{category} in {country}',
	'category_langbox_entry_attr-title' => '',

	'country_title' => '{category} in {country} | {sitename}',
	'country_desc' => '',
	'country_h1' => '{country}',
	'country_h2' => 'Anbieter {category_shortdesc} in {country}',
	'country_h3' => '{lang}kurse',
	'country_always_use_h3' => 'yes',
	'country_citybox_heading' => '{city}',
	'country_citybox_entry_text' => '{school}',
	'country_citybox_entry_attr-title' => '{category} {lang} mit {school}',
	'country_citybox_more_text' => 'Mehr über {city}',
	'country_citybox_more_attr-title' => '{category} {lang} in {city}',

	'city_title' => '{category} in {city}, {country} | {sitename}',
	'city_desc' => '',
	'city_h1' => '{city}',
	'city_h2' => 'Anbieter {category_shortdesc} in {city}',
	'city_h3' => '{lang}kurse',
	'city_always_use_h3' => 'no',
	'city_schoolbox_more_text' => 'Erfahren Sie mehr',
	'city_schoolbox_more_attr-title' => '{school}, {category} {lang} in {city}',

	'school_title' => '{category} {lang} in {city}, {school}',
	'school_desc' => '',
	'school_course_heading' => '{course}'
);

//Warning: Do not change once set, this value influences database keys, JS functionality and CSS classes!
$GLOBALS['fbk_cf_prefix'] = '_fbk-cf_';

//WARNING! This array is not really a legitimate options array! Both display and storage functions are highly customized to each box and field.
$GLOBALS['fbk_cf_boxes'] = array(
	'courses' => array(
		'title' => 'Kurse',
		'context' => 'normal',
		'priority' => 'low',
		'type' => 'lines',
		'expandable' => true,
		'fields' => array(
			'cost' => array(
				'type' => 'coursestack',
				'label' => 'Preise',
				'collapse' => 1
			),
			'name' => array('label' => 'Kursbezeichnung', 'type' => 'text'),
			'hpw' => array('label' => 'Lektionen/Woche', 'type' => 'shorttext'),
			'hidden' => array('label' => 'Verstecken', 'type' => 'checkbox' ),
			'mpl' => array('label' => 'Lektionsdauer (Min)', 'type' => 'shorttext', 'collapse' => 1),
			'size' => array('label' => 'Klassengröße (min, Ø, max)', 'type' => 'multitext', 'nfields' => 3, 'separator' => '&nbsp;', 'collapse' => 1),
			'dur' => array('label' => 'Kursdauer in Wochen', 'type' => 'multitext', 'nfields' => 2,
				'separator' => '-', 'collapse' => 1),
			'lvl' => array('label' => 'Sprachniveau (min, max)', 'type' => 'collection', 'collapse' => 1, 'separator' => '-',
				'include' => array(
					'min' => array( 'type' => 'select', 'opts' => array( '', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ) ),
					'max' => array( 'type' => 'select', 'opts' => array( '', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ) )
				)
			),
			'fee' => array('label' => 'Anmeldegebühr', 'type' => 'shorttext', 'collapse' => 1),
			'bu' => array('label' => 'Bildungsurlaub', 'type' => 'checkbox', 'collapse' => 1),
			'tag' => array('label' => 'Schlagwörter', 'type' => 'course_tag', 'collapse' => 1),
			'desc' => array('label' => '', 'type' => 'wysiwyg', 'collapse' => 1)
		),
		'req' => 'name',
		'shared' => true
	),
	'accommodation' => array(
		'title' => 'Unterkunft',
		'context' => 'normal',
		'priority' => 'low',
		'type' => 'lines',
		'expandable' => true,
		'fields' => array(
			'name' => array('label' => 'Bezeichnung', 'type' => 'text'),
			'type' => array('label' => 'Art d. Unterkunft', 'type' => 'select',
				'opts' => array(
					'fam' => 'Gastfamilie',
					'hot' => 'Hotel',
					'res' => 'Studentenwohnheim',
					'wg' => 'Wohngemeinschaft',
					'ap' => 'Appartement'
				)
			),
			'fee' => array('label' => 'Vermittlungsgebühr', 'type' => 'text'),
			'hidden' => array('label' => 'Verstecken', 'type' => 'checkbox' ),
			'cost' => array( 'label' => 'Preise', 'type' => 'accstack', 'collapse' => 1 ),
			'desc' => array('label' => '', 'type' => 'wysiwyg', 'collapse' => true)
		),
		'req-one_of' => array( 'name', 'desc' ),
		'shared' => true
	),
	'leisure' => array(
		'title' => 'Freizeit',
		'context' => 'advanced',
		'priority' => 'default',
		'type' => 'single',
		'fields' => array(
			'type' => 'wysiwyg', 'instantly_active' => true
		)
	),
	'fees' => array(
		'title' => 'Diverse Leistungen &amp; Gebühren',
		'context' => 'advanced',
		'priority' => 'default',
		'type' => 'lines',
		'fields' => array(
			'key' => array('label' => 'Bezeichnung', 'type' => 'text'),
			'desc' => array('label' => 'Kurze Beschreibung', 'type' => 'longtext'),
			'cost' => array('label' => 'Preis', 'type' => 'shorttext', 'numeric' => true),
			'type' => array('label' => 'Zahlungsart', 'type' => 'select',
				'opts' => array(
					'wb' => 'Wöchentlich, zahlbar bei der Buchung',
					'eb' => 'Einmalig, zahlbar bei der Buchung',
					'wo' => 'Wöchentlich, zahlbar vor Ort',
					'eo' => 'Einmalig, zahlbar vor Ort'
				)
			),
		),
		'req' => 'key',
		'shared' => true
	),
	'geo' => array(
		'title' => 'Standort',
		'context' => 'side',
		'priority' => 'low',
		'type' => 'normal',
		'fields' => array(
			'street' => array('label' => 'Straße', 'type' => 'text'),
			'postalcode' => array('label' => 'Postleitzahl', 'type' => 'text'),
			'region' => array('label' => 'Region', 'type' => 'text'),
			'lat-long' => array(
				'type' => 'text',
				'label' => "Latitude, Longitude <a href='%__url__%' target='_blank' "
				. "title='Hier klicken, um nach der Schule zu suchen. &bull; Auf den Schulstandort rechtsklicken und &bdquo;Was ist hier?&ldquo; wählen."
				. " &bull; Die Suchleiste zeigt dann den genauen Standort als &bdquo;Latitude, Longitude&ldquo; an. &bull; "
				. "Diese beiden Werte unverändert hier einfügen.'>[?]</a>",
				'callback' => array( 'label' => '_fbk_cf_insert_googlemaps_search_url' )
			)
		)
	),
	'internal' => array(
		'title' => 'Interne Angaben',
		'context' => 'side',
		'priority' => 'default',
		'type' => 'normal',
		'fields' => array(
			'comm' => array('label' => 'Kommission (%)', 'type' => 'text'),
			'web' => array('label' => 'Webseite', 'type' => 'text'),
			'contact' => array('label' => 'Kontaktperson', 'type' => 'text'),
			'mail' => array('label' => 'Kontaktperson: E-Mail-Adresse', 'type' => 'text'),
			'tel' => array('label' => 'Kontaktperson: Telefon', 'type' => 'text'),
			'fax' => array('label' => 'Kontaktperson: Fax', 'type' => 'text'),
			'comments' => array('label' => 'Bemerkungen', 'type' => 'textarea'),
			'upd' => array('label' => 'Letztes Update', 'type' => 'date', 'default' => date('d.m.Y'))
		)
	)
);

function _fbk_cf_insert_googlemaps_search_url( $str ) {
	global $post;
	$city = wp_get_object_terms( $post->ID, 'loc', array('fields'=>'names') );
	$search = $post->post_title . ' ' . @$city[0];
	return str_replace( '%__url__%', "http://maps.google.de/maps?q=" . urlencode($search), $str );
}

?>
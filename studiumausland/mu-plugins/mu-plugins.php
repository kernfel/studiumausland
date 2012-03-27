<?php
/**
 * Must-use plugin loader. Must be included by a file in WP's mu-plugins directory.
 * @package Studium_Ausland
 */

$mydir = dirname(__FILE__);

require( $mydir . '/l10n-disabler.php' );
require( $mydir . '/sitemap-generator.php' );
<?php
/*
Plugin Name: Felix' l10n disabler
Plugin URI: http://www.studium-ausland.eu/
Description: Overrides load_textdomain for all requests to the front end
Author: Felix Kern
*/

if ( ! is_admin() )
	add_filter( 'override_load_textdomain', '__return_true' );
?>
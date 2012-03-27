<?php
/*
Plugin Name: Studium-Ausland must-use plugins
Description: A set of plugins that should really be part of the theme, but can't, because they need to run before the theme is loaded
Author: Felix Kern
License: GPL2
*/
/**
 * @package Studium_Ausland
 */
if ( get_option( 'fbk_theme_active' ) && file_exists( $path = get_theme_root() . '/studiumausland/mu-plugins/mu-plugins.php' ) ) {
	require( $path );
}
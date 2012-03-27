<?php
/**
 * @package Studium_Ausland
 */

if ( ! is_front_page() )	
	the_title( '<h1>', '</h1>' );
the_content();

?>
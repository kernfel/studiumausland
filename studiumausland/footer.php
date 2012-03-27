<?php
/**
 * @package Studium_Ausland
 */

global $fbk, $fbk_cache;
$home = home_url();

?>
</div></div></div><?php // #pagewrap, .w1k, #page ?>
<footer>
<?php

if ( ! $fbk_cache->get_e( 'footer' ) ) :
	$fbk_cache->rec();

	$terms = $cat_to_ctry = array();
	foreach ( get_terms( 'loc', array( 'get' => 'all' ) ) as $term )
		$terms[$term->term_id] = $term;
	$pattern = "/^(\\d+)-\\d+-(\\d+)-/";
	foreach ( get_option( 'fbk_menu_cache' ) as $tuple ) {
		preg_match( $pattern, $tuple, $matches );
		$cat_to_ctry[ $matches[1] ][ $matches[2] ] = $terms[$matches[2]];
	}

?>
 <nav id="tab" class="w1k">
  <ul class="catbar">
<?php foreach ( $fbk->cats as $cat ) : ?>
   <li class="scat" style="width:<?= $cat->fbk_width ?>px;">
    <a href="<?= get_term_link( $cat ) ?>" class="c-<?= $cat->slug ?>-full cat-<?= $cat->term_id ?>"><?= $cat->name ?></a>
    <ul class="c-<?= $cat->slug ?>" style="width:<?= $cat->fbk_width - 2 ?>px;">
<?php	foreach ( $cat_to_ctry[$cat->term_id] as $country ) : ?>
     <li><a href="<?= "$home/$cat->slug/$country->slug" ?>" class="loc-<?= $country->term_id ?>" title="<?=
      fbk_get_category_meta( 'menu_level_1', array( 'category' => $cat, 'country' => $country, 'no_defaults' => true ) ) ?>"><?= $country->name ?></a></li>
<?php	endforeach; ?>
    </ul>
   </li>
<?php endforeach; ?>
  </ul>
 </nav>
<?php
	$fbk_cache->done( 'footer' );
endif;

?>
 <div id="footer">  
  <p>&copy; <?= date('Y') ?> <a href="<?= $home ?>"><?= get_bloginfo('name') ?></a> | <a href="<?= $home . '/agb' ?>">AGB</a> | <a href="<?= $home . '/impressum' ?>">Impressum</a> | <a href="<?= $feed_url = esc_url(get_feed_link()) ?>" target="_blank" class="rss">RSS</a> | <a href="<?= admin_url() ?>">Admin-Login</a> | Powered by <a href="http://www.wordpress.org/" id="wp">WordPress</a></p>
 </div>
 <script src="https://apis.google.com/js/plusone.js">{lang: 'de', parsetags: 'explicit'}</script>
 <?php wp_footer(); ?>
</footer>
<div class="ad noheading ad-fullwidth" id="rss-pop"><a href="<?= $home . '/news' ?>" id="rss-pop-a" class="news-1"></a>Bleiben Sie auf dem Laufenden über unsere Angebote und Sonderaktionen. Besuchen Sie unsere Newsseite und <a href="<?= $feed_url ?>" target="_blank">abonnieren Sie unseren RSS-Feed</a>!<div class="close">Schließen</div></div>
<script>
jQuery(function($){
var a={store:'session'},l,o,c=$('#rss-pop'),u=function(){if(Util.ready&&'index'==Ajax.state.object&&!Ajax.fading&&!Util.retrieve('rsspop-x',a)){
l||/MSIE [1-8]\./.test(navigator.userAgent)||$.getScript('<?= get_stylesheet_directory_uri() ?>/lib/canvasloader.js',function(){l=new CanvasLoader('rss-pop');l.setDiameter(27);l.setRange(1);l.setSpeed(1);l.setFading(!1);l.setScaling(!0);l.setDensity(70);l.setColor('#f20a48');});
$('.close,a',c).click(function(){Util.store('rsspop-x',1,a);return c.fadeOut(500);});
o=$('#news').offset();o.left+=117;c.css(o).fadeIn(500);
}};
$(window).bind('statechange',function(){if('index'==History.getState().data.object)setTimeout(u,1500);else c.fadeOut(250);});
setTimeout(u,1000);
});
</script>
</body>
</html>
<?php
// Add Shortcode
function enjoyinstagram_mb_shortcode($atts) {
	$shortcode_content = '';
STATIC $i = 1;


	if(get_option('enjoyinstagram_client_id')!==null || get_option('enjoyinstagram_client_id') != '') {
	extract( shortcode_atts( array(
		'n' => '4',
	), $atts ) );
?>
<script>
    jQuery(function(){
      jQuery(document.body)
          .on('click touchend','#swipebox-slider .current img', function(e){
              jQuery('#swipebox-next').click();
			  return false;
          })
          .on('click touchend','#swipebox-slider .current', function(e){
              jQuery('#swipebox-close').trigger('click');
          });
    });
</script>
<script type="text/javascript">
jQuery(function($) {
	$(".swipebox").swipebox({
	hideBarsDelay : 0
	});

});
jQuery(document).ready(function() {
jQuery("#owl-<?php echo $i; ?>").owlCarousel({
	  lazyLoad : true,
	  items : <?php echo get_option('enjoyinstagram_carousel_items_number'); ?>,
	  itemsDesktop : [1199,<?php echo get_option('enjoyinstagram_carousel_items_number'); ?>],
   	  itemsDesktopSmall : [980,<?php echo get_option('enjoyinstagram_carousel_items_number'); ?>],
      itemsTablet: [768,<?php echo get_option('enjoyinstagram_carousel_items_number'); ?>],
      itemsMobile : [479,<?php echo get_option('enjoyinstagram_carousel_items_number'); ?>],
	  stopOnHover: true,
	  navigation: <?php echo get_option('enjoyinstagram_carousel_navigation'); ?>

		 });
		 jQuery("#owl-<?php echo $i; ?>").fadeIn();
		 });
</script>
<?php

if(get_option('enjoyinstagram_user_or_hashtag')=='hashtag'){
	$result = get_hash(urlencode(get_option('enjoyinstagram_hashtag')),20);
	$result = $result['data'];
}else{
	$result = get_user(urlencode(get_option('enjoyinstagram_user_username')),20);
	$result = $result['data'];
}
$pre_shortcode_content = "<div id=\"owl-".$i."\" class=\"owl-example\" style=\"display:none;\">";


		if (isHttps()&& !is_null($result)) {
			foreach ($result as $entry) {
				$entry['images']['thumbnail']['url'] = str_replace('http://', 'https://', $entry['images']['thumbnail']['url']);
				$entry['images']['standard_resolution']['url'] = str_replace('http://', 'https://', $entry['images']['standard_resolution']['url']);
			}
		}



if(!is_null($result)){
foreach ($result as $entry) {
	if(!empty($entry['caption'])) {
		$caption = $entry['caption']['text'];
	}else{
		$caption = '';
	}
	$square_thumbnail = $entry['images']['thumbnail']['url'];

	$link = "<a title=\"{$caption}\" rel=\"gallery_swypebox\" class=\"swipebox\" href=\"{$entry['images']['standard_resolution']['url']}\">";

		if(($entry['type']=='video')){
			$video_link = str_replace("href=\"".$entry['images']['standard_resolution']['url']."\">","href=\"{$entry['videos']['standard_resolution']['url']}\">",$link);
			$shortcode_content .=  "<div class=\"box\">" . $video_link . "<img alt=\"{$caption}\" src=\"{$square_thumbnail}\"></a></div>";
		}else{
			if(get_option('enjoyinstagram_carousel_items_number')!='1'){
					$shortcode_content .=  "<div class=\"box\">". $link ."<img  src=\"{$square_thumbnail}\"></a></div>";
				}else{
					$shortcode_content .=  "<div class=\"box\"><a title=\"{$caption}\" rel=\"gallery_swypebox\" class=\"swipebox\" href=\"{$entry['images']['standard_resolution']['url']}\"><img style=\"width:100%;\" src=\"{$square_thumbnail}\"></a></div>";
				}
			}
		}



}

$post_shortcode_content = "</div>";



}
$i++;

$shortcode_content = $pre_shortcode_content.$shortcode_content.$post_shortcode_content;

return $shortcode_content;

}
add_shortcode( 'enjoyinstagram_mb', 'enjoyinstagram_mb_shortcode' );




?>

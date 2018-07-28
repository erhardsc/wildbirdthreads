<?php get_header(); ?>

		<!-- Top Section -->
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>	
	
		<?php get_template_part( 'partials/content', 'top'); ?>
		<!-- Top Section End-->
		
		<!-- Slider Section End-->
		<?php get_template_part( 'partials/content', 'slider'); ?>
		<!-- Slider Section End-->
		
		<!-- Quote Section -->
		<?php get_template_part( 'partials/content', 'quote'); ?>
		<!-- Quote Section End-->
		
		<!-- Q&A Section -->
		<?php get_template_part( 'partials/content', 'QandA'); ?>
		<!-- Q&A Section End-->
		
		<!-- Social Media Section -->
		<?php get_template_part( 'partials/content', 'social'); ?>
		<!-- Social Media Section END-->
	
	
	<?php endwhile; endif; ?>

<?php get_footer(); ?>
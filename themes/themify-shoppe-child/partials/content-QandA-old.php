<?php
	// Allow a maximum of 10 questions

// Custom Excerpt function for Advanced Custom Fields
function custom_field_excerpt($field) {
	global $post;
	$text = get_field("$field"); //Replace 'your_field_name'
	if ( '' != $text ) {
		$text = strip_shortcodes( $text );
		$text = apply_filters('the_content', $text);
		$text = str_replace(']]&gt;', ']]&gt;', $text);
		$excerpt_length = 20; // 20 words
		$excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
		$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
	}
	return apply_filters('the_excerpt', $text);
}
	 
	global $count;
	for( $count = 1; $count <= 10; $count++ ) : if ( get_field( "question_$count" ) ) :	?>

	<?php if ( $count % 2 != 0 ) : ?>
	
		<?php if( get_field( "question_$count" ) ): ?>
		
			<?php if( empty(get_field( "question_image_$count" ) ) ): ?>
				<div class="container-fluid question-answer <?php echo "question-answer-$count"; ?>">
					<div class="row">
						<div class="col-md-12 artist-question text-center">
				 			<h3><?php the_field( "question_$count" ); ?></h3>
				 			<p><?php custom_field_excerpt( get_field( "answer_$count" ) ); ?><p>
						</div>
					</div>
				</div>
			
			<?php else: ?>
			<div class="container-fluid question-answer <?php echo "question-answer-$count"; ?>">
				<div class="row">
					<div class="col-md-6 artist-question">
			 			<h3><?php the_field( "question_$count" ); ?></h3>
			 			<p><?php custom_field_excerpt( get_field( "answer_$count" ) ); ?><p>
					</div>
					<?php 
						$image = get_field( "question_image_$count" );
						if ( !empty( $image )): ?>
						<div class="col-md-offset-6 image-cropper">
							<img class="round" src="<?php echo $image['url']; ?>" />
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php endif; ?>
	
	<?php else : ?>
	
		<?php if( get_field( "question_$count" ) ): ?>
		
			<?php if( empty(get_field( "question_image_$count" ) ) ): ?>
				<div class="container-fluid container-dark question-answer <?php echo "question-answer-$count"; ?>">
					<div class="row">
						<div class="col-md-12 artist-question text-center">
				 			<h3><?php the_field( "question_$count" ); ?></h3>
				 			<p><?php the_field( "answer_$count" ); ?><p>
						</div>
					</div>
				</div>
			
			<?php else: ?>
			<div class="container-fluid container-dark question-answer <?php echo "question-answer-$count"; ?>">
				<div class="row">
					<?php 
						$image = get_field( "question_image_$count" );
						if ( !empty( $image )): ?>
						<div class="col-md-6 image-cropper">
							<img class="round" src="<?php echo $image['url']; ?>" />
						</div>
					<?php endif; ?>
					<div class="col-md-offset-6 artist-question">
			 			<h3 class="text-right"><?php the_field( "question_$count" ); ?></h3>
			 			<p class="text-right"><?php the_field( "answer_$count" ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php endif; ?>
	
	<?php endif; ?>
	
<?php else :?>

	<?php break; ?>

<?php endif; endfor; ?>
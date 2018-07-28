<?php
if( have_rows('question_answer') ):
	$i = 1;
while ( have_rows('question_answer') ) : the_row();

if ( is_even($i) ): //row is even
	if ( empty(get_sub_field( 'image' ) ) ): ?>
		<div class="container-fluid container-dark question-answer question-answer-even <?php echo "question-answer-$i"; ?>">
					<div class="row">
						<div class="col-md-12 artist-question text-center">
				 			<h3><?php the_sub_field('question'); ?></h3>
				 			<div id="excerpt-<?php echo $i ?>" class="excerpt"><?php echo custom_field_excerpt( get_sub_field('answer') ); ?></div>
				 			<div id="content-<?php echo $i ?>" class="content"><p><?php the_sub_field('answer'); ?></p></div>
						</div>
					</div>
				</div>
	<?php else : //even row has image object ?>
		<div class="container-fluid container-dark question-answer question-answer-even <?php echo "question-answer-$i"; ?>">
				<div class="row">
					<div class="col-md-6 artist-question">
			 			<h3><?php the_sub_field('question'); ?></h3>
				 		<div id="excerpt-<?php echo $i ?>" class="excerpt"><?php echo custom_field_excerpt( get_sub_field('answer') ); ?></div>
				 		<div id="content-<?php echo $i ?>" class="content"><p><?php the_sub_field('answer'); ?></p></div>
					</div>
					<?php 
						$image = get_sub_field( 'image' );
						if ( !empty( $image )): ?>
						<div class="col-md-offset-6 image-cropper">
							<img class="round" src="<?php echo $image['url']; ?>" />
						</div>
					<?php endif; ?>
				</div>
			</div>
	<?php endif; ?>	
<?php else : //row is odd 
	if ( empty(get_sub_field( 'image' ) ) ): ?>
		<div class="container-fluid question-answer question-answer-odd <?php echo "question-answer-$i"; ?>">
					<div class="row">
						<div class="col-md-12 artist-question text-center">
				 			<h3><?php the_sub_field('question'); ?></h3>
				 			<div id="excerpt-<?php echo $i ?>" class="excerpt"><?php echo custom_field_excerpt( get_sub_field('answer') ); ?></div>
				 			<div id="content-<?php echo $i ?>" class="content"><p><?php the_sub_field('answer'); ?></p></div>
						</div>
					</div>
				</div>
	
	<?php else : //odd row has image object ?>
		<div class="container-fluid question-answer question-answer-odd <?php echo "question-answer-$i"; ?>">
				<div class="row">
					<?php 
						$image = get_sub_field( 'image');
						if ( !empty( $image )): ?>
						<div class="col-md-6 image-cropper">
							<img class="round" src="<?php echo $image['url']; ?>" />
						</div>
					<?php endif; ?>
					<div class="col-md-offset-6 artist-question">
			 			<h3 class="text-right"><?php the_sub_field( 'question' ); ?></h3>
			 			<div id="excerpt-<?php echo $i ?>" class="excerpt"><?php echo custom_field_excerpt( get_sub_field('answer') ); ?></div>
				 		<div id="content-<?php echo $i ?>" class="content"><p><?php the_sub_field('answer'); ?></p></div>
					</div>
				</div>
			</div>
	<?php endif; ?>	
<?php endif; ?>
<?php
++$i;

endwhile;

else :

	// no rows found

	endif; ?>
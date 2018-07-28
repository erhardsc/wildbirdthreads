<?php 
	global $first_name; 
	global $title;
?>

<div class="container-dark">
	<div id="artist-title" class="row">
		<h2 class="fancy-heading"><?php 
			
			if ( two_artists($title) ) {
						echo $title;
					} else {
						echo $first_name;
					}
			
		?>'s work</h2>
	</div>
	<div id="artist-slider" class="row">
		<div class="col-md-12 slider">
			<?php the_field( 'work' ); ?>
		</div>
	</div>
</div>
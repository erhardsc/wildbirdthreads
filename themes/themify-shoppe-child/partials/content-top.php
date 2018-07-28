<div id="featured-artist-top" class="container-fluid">
	<!-- Title -->
	<div id="wrapper" class="padding-top">
		<div class="row">
			<div id="featured-artist" class="col-md-12">
				<h1 class="artist-title animated fadeInDown"><?php the_title(); ?></h1>
				<h4><?php the_field( 'artist_location' ); ?></h4>
				
				<?php if ( get_field( 'artist_website' ) ) : ?>
			  		<a href="http://<?php echo get_field( 'artist_website' ); ?>" target="_blank"><?php the_field( 'artist_website' ); ?></a>
			  	<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<div id="featured-artist-top-below" class="container-fluid padding-top">
	<!-- About Artist Container -->
	<div id="about-artist" class="row">
		<div class="col-md-12">
			<div class="image-cropper animated fadeInUp">

				<?php if (has_post_thumbnail() && empty( get_field( 'profile_image' ) ) ): ?>
					  <?php echo get_the_post_thumbnail( $page->ID, 'large' ); ?>
				<?php else : ?>
					<?php $image = get_field( 'profile_image' ); ?>
					<img id="coverImage" src="<?php echo $image['url']; ?>" />
				<?php endif; ?>
<!--
			<?php 
				$image = get_field( 'profile_image' );
				if ( !empty( $image )): ?>
				
					<img src="<?php echo $image['url']; ?>" />
			<?php endif; ?>	
-->	
			</div>
		</div>
		<div id="about-artist about-artist-text" class="row">
			<div class="col-md-12 box animated fadeInLeft">
			<h2 class="text-center">About 
				<?php
					global $first_name;
					global $title;
					function two_artists($artist_name) {
						$artist = false;
					
					    if ( preg_match("/[+&]|(\band)|(\w'n\b)/",$artist_name) ){ // Any string that contains + & and 'n ( i.e. Anything that can join two words together display both words).
								$artist = true;
							} 
						return $artist;
					}
					$title = get_the_title();
					$title_array = explode( ' ', $title );
					$first_name = $title_array[0]; 
					
					
					if ( two_artists($title) ) {
						echo $title;
					} else {
						echo $first_name;
					}
				?></h2>
			<p><?php the_field( 'artist_about' ); ?></p>
		</div>
		</div>
		<div class="col-md-3">
	</div>
</div>
</div>
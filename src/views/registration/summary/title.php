<?php
/**
 * This template renders the summary Title
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/registration/summary/title.php
 *
 * @version 4.9
 *
 */
?>
<div class="tribe-block__tickets__registration__title">
	<header>
		<h2>
			<a href="<?php the_permalink( $event_id ); ?>">
				<?php echo get_the_title( $event_id ); ?>
			</a>
		</h2>
	</header>
</div>

<?php
/**
 * Block: RSVP
 * Messages Must Login
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/v2/rsvp/messages/must-login.php
 *
 * See more documentation about our Blocks Editor templating system.
 *
 * @link {INSERT_ARTICLE_LINK_HERE}
 *
 * @param WP_Post|int $post_id The post object or ID.
 * @var bool $must_login Whether the user has to login to RSVP or not.
 *
 * @since TBD
 *
 * @version TBD
 */

if ( ! $must_login ) {
	return;
}

?>
<div class="tribe-tickets__rsvp-message tribe-tickets__rsvp-message--must-login tribe-common-b3">
	<em class="tribe-common-svgicon tribe-tickets__rsvp-message--must-login-icon"></em>

	<span class="tribe-tickets__rsvp-message-text">
		<strong>
			<?php
			echo esc_html(
				sprintf(
					/* Translators: 1: RSVP label. */
					_x( 'You must be logged in to %1$s', 'rsvp must login', 'event-tickets' ),
					tribe_get_rsvp_label_singular( 'rsvp_must_login' )
				)
			);
			?>

			<a
				href="<?php echo esc_url( Tribe__Tickets__Tickets::get_login_url( $post_id ) . '?tribe-tickets__rsvp' . $rsvp->ID ); ?>"
				class="tribe-tickets__rsvp-message-link"
			>
				<?php esc_html_e( 'Log in here', 'event-tickets' ); ?>
			</a>
		</strong>
	</span>
</div>

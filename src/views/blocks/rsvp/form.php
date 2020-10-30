<?php
/**
 * Block: RSVP.
 * Form from rsvp/form/form.php via AJAX.
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/blocks/rsvp/form.php
 *
 * See more documentation about our Blocks Editor templating system.
 *
 * @link    https://m.tri.be/1amp Help article for RSVP & Ticket template files.
 *
 * @since   4.9
 * @since   4.12.0 Add $post_id to filter for hiding opt-outs.
 * @since   TBD Add vars to docblock and removed duplicative vars.
 *
 * @version TBD
 *
 * @var Tribe__Tickets__Editor__Template $this                Template object.
 * @var int                              $post_id             [Global] The current Post ID to which RSVPs are attached.
 * @var array                            $attributes          [Global] RSVP attributes (could be empty).
 * @var Tribe__Tickets__Ticket_Object[]  $active_rsvps        [Global] List of RSVPs.
 * @var bool                             $all_past            [Global] True if RSVPs availability dates are all in the past.
 * @var bool                             $has_rsvps           [Global] True if the event has any RSVPs.
 * @var bool                             $has_active_rsvps    [Global] True if the event has any RSVPs available.
 * @var bool                             $must_login          [Global] True if only logged-in users may obtain RSVPs.
 * @var string                           $login_url           [Global] The site's login URL.
 * @var int                              $threshold           [Global] The count at which "number of tickets left" message appears.
 * @var null|string                      $step                [Global] The point we're at in the loading process.
 * @var bool                             $opt_in_checked      [Global] Whether appearing in Attendee List was checked.
 * @var string                           $opt_in_attendee_ids [Global] The list of attendee IDs to send in the form submission.
 * @var string                           $opt_in_nonce        [Global] The nonce for opt-in AJAX requests.
 * @var bool                             $doing_shortcode     [Global] True if detected within context of shortcode output.
 * @var bool                             $block_html_id       [Global] The RSVP block HTML ID. $doing_shortcode may alter it.
 * @var Tribe__Tickets__Ticket_Object    $ticket              The ticket object with provider set to RSVP.
 * @var string                           $going               The RSVP status at time of add/edit (e.g. 'yes'), or empty if not in that context.
 */
?>

<!-- This div is where the AJAX returns the form. -->
<div class="tribe-block__rsvp__form">
	<?php
	if (
		! empty( $going )
		&& ! $must_login
	) :
		// Can't escape, contains HTML.
		$this->template( 'blocks/rsvp/form/form' );
	endif;
	?>
</div>

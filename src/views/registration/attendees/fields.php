<?php
/**
 * This template renders a the fields for a ticket
 *
 * @version TBD
 *
 */
?>
<div class="tribe-ticket">
    <h4><?php _e( 'Attendee', 'tribe_tickets' ); ?> <?php echo $key + 1; ?></h4>
	<?php foreach ( $fields as $field ) : ?>
		<?php $value = ! empty( $saved_meta[ $ticket->ID ][ $key ][ $field->slug ] ) ? $saved_meta[ $ticket->ID ][ $key ][ $field->slug ] : null; ?>
		<?php $this->template( 'attendees/fields/' . $field->type, array( 'ticket' => $ticket, 'field' => $field, 'value' => $value, 'key' => $key ) ); ?>
	<?php endforeach; ?>
</div>


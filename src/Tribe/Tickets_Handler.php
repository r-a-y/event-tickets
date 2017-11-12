<?php
/**
 * Handles most actions related to a Ticket or Multiple ones
 */
class Tribe__Tickets__Tickets_Handler {
	/**
	 * Post Meta key for the ticket header
	 * @var string
	 */
	protected $image_header_field = '_tribe_ticket_header';

	/**
	 * Post Meta key for the ticket order
	 *
	 * @since 4.6
	 *
	 * @var string
	 */
	protected $tickets_order_field = '_tribe_tickets_order';

	/**
	 * Post Meta key for event ecommerce provider
	 *
	 * @since 4.6
	 *
	 * @var string
	 */
	public $key_provider_field = '_tribe_default_ticket_provider';

	/**
	 * Post meta key for the ticket capacty
	 *
	 * @since  4.6
	 *
	 * @var    string
	 */
	public $key_capacity = '_tribe_ticket_capacity';

	/**
	 * Post meta key for the ticket start date
	 *
	 * @since  4.6
	 *
	 * @var    string
	 */
	public $key_start_date = '_ticket_start_date';

	/**
	 * Post meta key for the ticket end date
	 *
	 * @since  4.6
	 *
	 * @var    string
	 */
	public $key_end_date = '_ticket_end_date';

	/**
	 * Post meta key for the manual updated meta keys
	 *
	 * @since  4.6
	 *
	 * @var    string
	 */
	public $key_manual_updated = '_tribe_ticket_manual_updated';

	/**
	 * Meta data key we store show_description under
	 *
	 * @since 4.6
	 *
	 * @var string
	 */
	public $key_show_description = '_tribe_ticket_show_description';

	/**
	 * String to represent unlimited tickets
	 * translated in the constructor
	 *
	 * @since 4.6
	 *
	 * @var string
	 */
	public $unlimited_term = 'Unlimited';

	/**
	 *    Class constructor.
	 */
	public function __construct() {
		$main = Tribe__Tickets__Main::instance();
		$this->unlimited_term = __( 'Unlimited', 'event-tickets' );

		foreach ( $main->post_types() as $post_type ) {
			add_action( 'save_post_' . $post_type, array( $this, 'save_image_header' ) );
			add_action( 'save_post_' . $post_type, array( $this, 'save_order' ) );
		}

		add_action( 'tribe_tickets_attendees_event_details_list_top', array( $this, 'event_details_top' ), 20 );
		add_action( 'tribe_tickets_plus_report_event_details_list_top', array( $this, 'event_details_top' ), 20 );
		add_action( 'tribe_tickets_attendees_event_details_list_top', array( $this, 'event_action_links' ), 25 );
		add_action( 'tribe_tickets_plus_report_event_details_list_top', array( $this, 'event_action_links' ), 25 );
		add_action( 'wp_ajax_tribe-ticket-save-settings', array( $this, 'ajax_handler_save_settings' ) );


		add_filter( 'get_post_metadata', array( $this, 'filter_capacity_support' ), 15, 3 );
		add_filter( 'updated_postmeta', array( $this, 'update_shared_tickets_capacity' ), 15, 4 );

		add_filter( 'updated_postmeta', array( $this, 'update_meta_date' ), 15, 4 );
		add_action( 'wp_insert_post', array( $this, 'update_start_date' ), 15, 3 );
	}

	/**
	 * On updating a few meta keys we flag that it was manually updated so we can do
	 * fancy matching for the updating of the event start and end date
	 *
	 * @since  4.6
	 *
	 * @param  int     $meta_id         MID
	 * @param  int     $object_id       Which Post we are dealing with
	 * @param  string  $meta_key        Which meta key we are fetching
	 * @param  int     $event_capacity  To which value the event Capacity was update to
	 *
	 * @return int
	 */
	public function flag_manual_update( $meta_id, $object_id, $meta_key, $date ) {
		$keys = array(
			$this->key_start_date,
			$this->key_end_date,
		);

		// Bail on not Date meta updates
		if ( ! in_array( $meta_key, $keys ) ) {
			return;
		}

		$updated = get_post_meta( $object_id, $this->key_manual_updated );

		// Bail if it was ever manually updated
		if ( in_array( $meta_key, $updated ) ) {
			return;
		}

		// the updated metakey to the list
		add_post_meta( $object_id, $this->key_manual_updated, $meta_key );

		return;
	}

	/**
	 * Verify if we have Manual Changes for a given Meta Key
	 *
	 * @since  4.6
	 *
	 * @param  int|WP_Post  $ticket  Which ticket/post we are dealing with here
	 * @param  string|null  $for     If we are looking for one specific key or any
	 *
	 * @return boolean
	 */
	public function has_manual_update( $ticket, $for = null ) {
		if ( ! $ticket instanceof WP_Post ) {
			$ticket = get_post( $ticket );
		}

		if ( ! $ticket instanceof WP_Post ) {
			return false;
		}

		$updated = get_post_meta( $ticket->ID, $this->key_manual_updated );

		if ( is_null( $for ) ) {
			return ! empty( $updated );
		}

		return in_array( $for, $updated );
	}

	/**
	 * Allow us to Toggle flaging the update of Date Meta
	 *
	 * @since   4.6
	 *
	 * @param   boolean  $toggle  Should activate or not?
	 *
	 * @return  void
	 */
	public function toggle_manual_update_flag( $toggle = true ) {
		if ( true === (bool) $toggle ) {
			add_filter( 'updated_postmeta', array( $this, 'flag_manual_update' ), 15, 4 );
		} else {
			remove_filter( 'updated_postmeta', array( $this, 'flag_manual_update' ), 15 );
		}
	}

	/**
	 * On update of the Event End date we update the ticket end date
	 * if it wasn't manually updated
	 *
	 * @since  4.6
	 *
	 * @param  int     $meta_id    MID
	 * @param  int     $object_id  Which Post we are dealing with
	 * @param  string  $meta_key   Which meta key we are fetching
	 * @param  string  $date       Value save on the DB
	 *
	 * @return boolean
	 */
	public function update_meta_date( $meta_id, $object_id, $meta_key, $date ) {
		$meta_map = array(
			'_EventEndDate' => $this->key_end_date,
		);

		// Bail when it's not on the Map Meta
		if ( ! isset( $meta_map[ $meta_key ] ) ) {
			return false;
		}

		$event_types = Tribe__Tickets__Main::instance()->post_types();
		$post_type = get_post_type( $object_id );

		// Bail on non event like post type
		if ( ! in_array( $post_type, $event_types ) ) {
			return false;
		}

		$update_meta = $meta_map[ $meta_key ];
		$tickets = $this->get_tickets_ids( $object_id );

		foreach ( $tickets as $ticket ) {
			// Skip tickets with manual updates to that meta
			if ( $this->has_manual_update( $ticket, $update_meta ) ) {
				continue;
			}

			update_post_meta( $ticket, $update_meta, $date );
		}

		return true;
	}

	/**
	 * Updates the Start date of all non-modified tickets when an Ticket supported Post is saved
	 *
	 * @since  4.6
	 *
	 * @param  int      $post_id  Which post we are updating here
	 * @param  WP_Post  $post     Object of the current post updating
	 * @param  boolean  $update   If we are updating or creating a post
	 *
	 * @return boolean
	 */
	public function update_start_date( $post_id, $post, $update ) {
		// Bail on Revision
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		// Bail if the CPT doens't accept tickets
		if ( ! tribe_tickets_post_type_enabled( $post->post_type ) ) {
			return false;
		}

		$update_meta = $this->key_start_date;
		$tickets = $this->get_tickets_ids( $post_id );

		foreach ( $tickets as $ticket ) {
			// Skip tickets with manual updates to that meta
			if ( $this->has_manual_update( $ticket, $update_meta ) ) {
				continue;
			}

			// 30 min
			$round = 30;
			if ( class_exists( 'Tribe__Events__Main' ) ) {
				$round = (int) tribe( 'tec.admin.event-meta-box' )->get_timepicker_step( 'start' );
			}
			// Convert to seconds
			$round *= MINUTE_IN_SECONDS;

			$date = strtotime( $post->post_date );
			$date = round( $date / $round ) * $round;
			$date = date( Tribe__Date_Utils::DBDATETIMEFORMAT, $date );

			update_post_meta( $ticket, $update_meta, $date );
		}

		return true;
	}

	/**
	 * Gets the Tickets from a Post
	 *
	 * @since  4.6
	 *
	 * @param  int|WP_Post  $post
	 * @return array
	 */
	public function get_tickets_ids( $post = null ) {
		$modules = Tribe__Tickets__Tickets::modules();
		$args = array(
			'post_type'      => array(),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
			'order_by'       => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
			),
		);

		foreach ( $modules as $provider_class => $name ) {
			$provider = call_user_func( array( $provider_class, 'get_instance' ) );
			$module_args = $provider->get_tickets_query_args( $post );

			$args['post_type'] = array_merge( $args['post_type'], $module_args['post_type'] );
			$args['meta_query'] = array_merge( $args['meta_query'], $module_args['meta_query'] );
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * On update of the Event Capacity we will update all shared capacity Stock to match
	 *
	 * @since  4.6
	 *
	 * @param  int     $meta_id         MID
	 * @param  int     $object_id       Which Post we are dealing with
	 * @param  string  $meta_key        Which meta key we are fetching
	 * @param  int     $event_capacity  To which value the event Capacity was update to
	 *
	 * @return int
	 */
	public function update_shared_tickets_capacity( $meta_id, $object_id, $meta_key, $event_capacity ) {
		// Bail on non-capacity
		if ( $this->key_capacity !== $meta_key ) {
			return false;
		}

		$event_types = Tribe__Tickets__Main::instance()->post_types();

		// Bail on non event like post type
		if ( ! in_array( get_post_type( $object_id ), $event_types ) ) {
			return false;
		}

		$completes = array();
		$tickets = $this->get_tickets_ids( $object_id );

		foreach ( $tickets as $ticket ) {
			$mode = get_post_meta( $ticket, Tribe__Tickets__Global_Stock::TICKET_STOCK_MODE, true );

			if ( Tribe__Tickets__Global_Stock::GLOBAL_STOCK_MODE !== $mode ) {
				continue;
			}

			$totals = $this->get_ticket_totals( $ticket );
			$completes[] = $complete = $totals['pending'] + $totals['sold'];

			$stock = $event_capacity - $complete;
			update_post_meta( $ticket, '_stock', $stock );
		}

		// Make sure we are updating the Global Stock when we update it's capacity
		$shared_stock = new Tribe__Tickets__Global_Stock( $object_id );
		$shared_stock_level = $event_capacity - array_sum( $completes );
		$shared_stock->set_stock_level( $shared_stock_level );
	}

	/**
	 * Allows us to create capacity when none is defined for an older ticket
	 * It will define the new Capacity based on Stock + Tickets Pending + Tickets Sold
	 *
	 * Important to note that we cannot use `get_ticket()` or `new Ticket_Object` in here
	 * due to triggering of a Infinite loop
	 *
	 * @since  4.6
	 *
	 * @param  mixed   $value      Previous value set
	 * @param  int     $object_id  Which Post we are dealing with
	 * @param  string  $meta_key   Which meta key we are fetching
	 *
	 * @return int
	 */
	public function filter_capacity_support( $value, $object_id, $meta_key ) {
		// Something has been already set
		if ( ! is_null( $value ) ) {
			return $value;
		}

		// We only care about Capacity Key
		if ( $this->key_capacity !== $meta_key ) {
			return $value;
		}

		// We remove the Check to allow a fair usage of `metadata_exists`
		remove_filter( 'get_post_metadata', array( $this, 'filter_capacity_support' ), 15 );

		// Bail when we already have the MetaKey saved
		if ( metadata_exists( 'post', $object_id, $meta_key ) ) {
			return get_post_meta( $object_id, $meta_key, true );
		}

		// Do the migration
		$capacity = $this->migrate_object_capacity( $object_id );

		// Hook it back up
		add_filter( 'get_post_metadata', array( $this, 'filter_capacity_support' ), 15, 4 );

		return $capacity;
	}

	/**
	 * Migrates a given Post Object capacity from Legacy Version
	 *
	 * @since  4.6
	 *
	 * @param  int|WP_Post  $object  Which Post ID
	 *
	 * @return bool|int
	 */
	public function migrate_object_capacity( $object ) {
		if ( ! $object instanceof WP_Post ) {
			$object = get_post( $object );
		}

		if ( ! $object instanceof WP_Post ) {
			return false;
		}

		// Bail when we don't have a legacy version
		if ( ! tribe( 'tickets.version' )->is_legacy( $object->ID ) ) {
			return false;
		}

		// Defaults to null
		$capacity = null;

		if ( tribe_tickets_post_type_enabled( $object->post_type ) ) {
			$event_stock_obj = new Tribe__Tickets__Global_Stock( $object->ID );

			// Fetches the Current Stock Level
			$capacity = $event_stock_obj->get_stock_level();
			$tickets  = $this->get_tickets_ids( $object->ID );

			foreach ( $tickets as $ticket ) {
				// Indy tickets don't get added to the Event
				if ( ! $this->has_shared_capacity( $ticket ) ) {
					continue;
				}

				$totals = $this->get_ticket_totals( $ticket );

				$capacity += $totals['sold'] + $totals['pending'];
			}
		} else {
			// In here we deal with Tickets migration from legacy
			$mode = get_post_meta( $object->ID, Tribe__Tickets__Global_Stock::TICKET_STOCK_MODE, true );
			$totals = $this->get_ticket_totals( $object->ID );

			if ( Tribe__Tickets__Global_Stock::CAPPED_STOCK_MODE === $mode ) {
				$capacity = (int) trim( get_post_meta( $object->ID, Tribe__Tickets__Global_Stock::TICKET_STOCK_CAP, true ) );
				$capacity += $totals['sold'] + $totals['pending'];
			} elseif ( Tribe__Tickets__Global_Stock::GLOBAL_STOCK_MODE === $mode  ) {
				// When using Global we don't set a ticket cap
				$capacity = null;
			} elseif ( Tribe__Tickets__Global_Stock::OWN_STOCK_MODE === $mode  ) {
				$capacity = array_sum( $totals );
			} else {
				$capacity = -1;
			}

			// Fetch ticket event ID for Updating capacity on event
			$event_id = tribe_events_get_ticket_event( $object->ID );

			// Apply to the Event
			if ( ! empty( $event_id ) ) {
				$this->migrate_object_capacity( $event_id );
			}
		}

		// Bail when we didn't have a capacity
		if ( is_null( $capacity ) ) {
			// Also still update the version, so we don't hit this method all the time
			tribe( 'tickets.version' )->update( $object->ID );

			return false;
		}

		$updated = update_post_meta( $object->ID, $this->key_capacity, $capacity );

		// If we updated the Capacity for legacy update the version
		if ( $updated ) {
			tribe( 'tickets.version' )->update( $object->ID );
		}

		return $capacity;
	}

	/**
	 * Gets the Total of Stock, Sold and Pending for a given ticket
	 *
	 * @since  4.6
	 *
	 * @param  int|WP_Post  $ticket  Which ticket
	 *
	 * @return array
	 */
	public function get_ticket_totals( $ticket ) {
		if ( ! $ticket instanceof WP_Post ) {
			$ticket = get_post( $ticket );
		}

		if ( ! $ticket instanceof WP_Post ) {
			return false;
		}

		$provider = tribe_tickets_get_ticket_provider( $ticket->ID );

		$totals = array(
			'stock'   => get_post_meta( $ticket->ID, '_stock', true ),
			'sold'    => 0,
			'pending' => 0,
		);

		if ( $provider instanceof Tribe__Tickets_Plus__Commerce__EDD__Main ) {
			$totals['sold']    = $provider->stock()->get_purchased_inventory( $ticket->ID, array( 'publish' ) );
			$totals['pending'] = $provider->stock()->count_incomplete_order_items( $ticket->ID );
		} elseif ( $provider instanceof Tribe__Tickets_Plus__Commerce__WooCommerce__Main ) {
			$totals['sold']    = get_post_meta( $ticket->ID, 'total_sales', true );
			$totals['pending'] = $provider->get_qty_pending( $ticket->ID, true );
		} else {
			$totals['sold'] = get_post_meta( $ticket->ID, 'total_sales', true );
		}

		$totals = array_map( 'intval', $totals );

		// Remove Pending from total
		$totals['sold'] -= $totals['pending'];

		return $totals;
	}

	/**
	 * Returns whether a ticket has unlimited capacity
	 *
	 * @since   4.6
	 *
	 * @param   int|WP_Post|object  $ticket
	 *
	 * @return  bool
	 */
	public function is_ticket_managing_stock( $ticket ) {
		if ( ! $ticket instanceof WP_Post ) {
			$ticket = get_post( $ticket );
		}

		if ( ! $ticket instanceof WP_Post ) {
			return false;
		}

		// Defaults to managing Stock so we don't have Unlimited
		$manage_stock = true;

		// If it exists we use it
		if ( metadata_exists( 'post', $ticket->ID, '_manage_stock' ) ) {
			$manage_stock = get_post_meta( $ticket->ID, '_manage_stock', true );
		}

		return tribe_is_truthy( $manage_stock );
	}

	/**
	 * Returns whether a ticket has unlimited capacity
	 *
	 * @since   4.6
	 *
	 * @param   int|WP_Post|object  $ticket
	 *
	 * @return  bool
	 */
	public function is_unlimited_ticket( $ticket ) {
		return -1 === tribe_tickets_get_capacity( $ticket->ID );
	}

	/**
	 * Returns whether a ticket uses Shared Capacity
	 *
	 * @since   4.6
	 *
	 * @param   int|WP_Post|object  $ticket
	 *
	 * @return  bool
	 */
	public function has_shared_capacity( $ticket ) {
		if ( ! $ticket instanceof WP_Post ) {
			$ticket = get_post( $ticket );
		}

		if ( ! $ticket instanceof WP_Post ) {
			return false;
		}

		$mode = get_post_meta( $ticket->ID, Tribe__Tickets__Global_Stock::TICKET_STOCK_MODE, true );

		return Tribe__Tickets__Global_Stock::CAPPED_STOCK_MODE === $mode || Tribe__Tickets__Global_Stock::GLOBAL_STOCK_MODE === $mode;
	}

	/**
	 * Checks if there are any unlimited tickets, optionally by stock mode or ticket type
	 *
	 * @since 4.6
	 *
	 * @param int|object (null) $post Post or Post ID tickets are attached to
	 * @param string (null) the stock mode we're concerned with
	 *			can be one of the following:
	 *				Tribe__Tickets__Global_Stock::GLOBAL_STOCK_MODE ('global')
	 *				Tribe__Tickets__Global_Stock::CAPPED_STOCK_MODE ('capped')
	 *				Tribe__Tickets__Global_Stock::OWN_STOCK_MODE ('own')
	 * @param string (null) $provider_class the ticket provider class ex: Tribe__Tickets__RSVP
	 * @return boolean whether there is a ticket (within the provided parameters) with an unlimited stock
	 */
	public function has_unlimited_stock( $post = null, $stock_mode = null, $provider_class = null ) {
		$post_id = Tribe__Main::post_id_helper( $post );
		$tickets = Tribe__Tickets__Tickets::get_event_tickets( $post_id );

		foreach ( $tickets as $index => $ticket ) {
			// Eliminate tickets by stock mode
			if ( ! is_null( $stock_mode ) && $ticket->global_stock_mode() !== $stock_mode ) {
				unset( $tickets[ $ticket ] );
				continue;
			}

			// Eliminate tickets by provider class
			if ( ! is_null( $provider_class ) && $ticket->provider_class !== $provider_class ) {
				unset( $tickets[ $ticket ] );
				continue;
			}

			if ( $this->is_unlimited_ticket( $ticket ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the total event capacity.
	 *
	 * @since  4.6
	 *
	 * @param  int|object (null) $post Post or Post ID tickets are attached to
	 *
	 * @return int|null
	 */
	public function get_total_event_capacity( $post = null ) {
		$post_id            = Tribe__Main::post_id_helper( $post );
		$has_shared_tickets = 0 !== count( $this->get_event_shared_tickets( $post_id ) );
		$total              = 0;

		if ( $has_shared_tickets ) {
			$total = tribe_tickets_get_capacity( $post_id );
		}

		// short circuit unlimited stock
		if ( -1 === $total ) {
			return $total;
		}

		$tickets = Tribe__Tickets__Tickets::get_event_tickets( $post_id );

		// Bail when we don't have Tickets
		if ( empty( $tickets ) ) {
			return $total;
		}

		foreach ( $tickets as $ticket ) {
			// Skip shared cap Tickets as it's added when we fetch the total
			if (
				Tribe__Tickets__Global_Stock::CAPPED_STOCK_MODE === $ticket->global_stock_mode()
				|| Tribe__Tickets__Global_Stock::GLOBAL_STOCK_MODE === $ticket->global_stock_mode()
			) {
				continue;
			}

			$capacity = $ticket->capacity();

			if ( -1 === $capacity ) {
				$total = -1;
				break;
			}

			$total += $capacity;
		}

		return apply_filters( 'tribe_tickets_total_event_capacity', $total, $post_id );
	}

	/**
	 * Get an array list of unlimited tickets for an event.
	 *
	 * @since 4.6
	 *
	 * @param int|object (null) $post Post or Post ID tickets are attached to
	 *
	 * @return array list of tickets
	 */
	public function get_event_unlimited_tickets( $post = null ) {
		$post_id     = Tribe__Main::post_id_helper( $post );
		$tickets     = Tribe__Tickets__Tickets::get_event_tickets( $post_id );
		$ticket_list = array();

		if ( empty( $tickets ) ) {
			return $ticket_list;
		}

		foreach ( $tickets as $ticket ) {
			if ( ! $this->is_unlimited_ticket( $ticket ) ) {
				continue;
			}

			$ticket_list[] = $ticket;
		}

		return $ticket_list;
	}

	/**
	 * Get an array list of independent tickets for an event.
	 *
	 * @since 4.6
	 *
	 * @param int|object (null) $post Post or Post ID tickets are attached to
	 *
	 * @return array list of tickets
	 */
	public function get_event_independent_tickets( $post = null ) {
		$post_id     = Tribe__Main::post_id_helper( $post );
		$tickets     = Tribe__Tickets__Tickets::get_event_tickets( $post_id );
		$ticket_list = array();

		if ( empty( $tickets ) ) {
			return $ticket_list;
		}

		foreach ( $tickets as $ticket ) {
			if ( Tribe__Tickets__Global_Stock::OWN_STOCK_MODE != $ticket->global_stock_mode() || 'Tribe__Tickets__RSVP' === $ticket->provider_class ) {
				continue;
			}

			// Failsafe - should not include unlimited tickets
			if ( $this->is_unlimited_ticket( $ticket ) ) {
				continue;
			}

			$ticket_list[] = $ticket;
		}

		return $ticket_list;
	}

	/**
	 * Get an array list of RSVPs for an event.
	 *
	 * @since 4.6
	 *
	 * @param int|object (null) $post Post or Post ID tickets are attached to
	 *
	 * @return string list of tickets
	 */
	public function get_event_rsvp_tickets( $post = null ) {
		$post_id     = Tribe__Main::post_id_helper( $post );
		$tickets     = Tribe__Tickets__Tickets::get_event_tickets( $post_id );
		$ticket_list = array();

		if ( empty( $tickets ) ) {
			return $ticket_list;
		}

		foreach ( $tickets as $ticket ) {
			if ( 'Tribe__Tickets__RSVP' !== $ticket->provider_class ) {
				continue;
			}

			$ticket_list[] = $ticket;
		}

		return $ticket_list;
	}

	/**
	 * Get an array list of shared capacity tickets for an event.
	 *
	 * @since 4.6
	 *
	 * @param int|object (null) $post Post or Post ID tickets are attached to
	 *
	 * @return array list of tickets
	 */
	public function get_event_shared_tickets( $post = null ) {
		$post_id     = Tribe__Main::post_id_helper( $post );
		$tickets     = Tribe__Tickets__Tickets::get_event_tickets( $post_id );
		$ticket_list = array();

		if ( empty( $tickets ) ) {
			return $ticket_list;
		}

		foreach ( $tickets as $ticket ) {
			$stock_mode = $ticket->global_stock_mode();
			if ( empty( $stock_mode ) || Tribe__Tickets__Global_Stock::OWN_STOCK_MODE === $stock_mode ) {
				continue;
			}

			// Failsafe - should not include unlimited tickets
			if ( $this->is_unlimited_ticket( $ticket ) ) {
				continue;
			}

			$ticket_list[] = $ticket;
		}

		return $ticket_list;
	}

	/**
	 * Injects event post type
	 *
	 * @param int $event_id
	 */
	public function event_details_top( $event_id ) {
		$pto = get_post_type_object( get_post_type( $event_id ) );

		echo '
			<li class="post-type">
				<strong>' . esc_html__( 'Post type', 'event-tickets' ) . ': </strong>
				' . esc_html( $pto->label ) . '
			</li>
		';
	}

	/**
	 * Injects action links into the attendee screen.
	 *
	 * @param $event_id
	 */
	public function event_action_links( $event_id ) {
		$action_links = array(
			'<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '" title="' . esc_attr_x( 'Edit', 'attendee event actions', 'event-tickets' ) . '">' . esc_html_x( 'Edit Event', 'attendee event actions', 'event-tickets' ) . '</a>',
			'<a href="' . esc_url( get_permalink( $event_id ) ) . '" title="' . esc_attr_x( 'View', 'attendee event actions', 'event-tickets' ) . '">' . esc_html_x( 'View Event', 'attendee event actions', 'event-tickets' ) . '</a>',
		);

		/**
		 * Provides an opportunity to add and remove action links from the
		 * attendee screen summary box.
		 *
		 * @param array $action_links
		 */
		$action_links = (array) apply_filters( 'tribe_tickets_attendees_event_action_links', $action_links );

		if ( empty( $action_links ) ) {
			return;
		}

		echo wp_kses_post( '<li class="event-actions">' . join( ' | ', $action_links ) . '</li>' );
	}

	/**
	 * Includes the tickets metabox inside the Event edit screen
	 *
	 * @param WP_Post $post
	 */
	public function do_meta_box( $post ) {
		$start_date = date( 'Y-m-d H:00:00' );
		$end_date   = date( 'Y-m-d H:00:00' );
		$start_time = Tribe__Date_Utils::time_only( $start_date, false );
		$end_time   = Tribe__Date_Utils::time_only( $start_date, false );

		$show_global_stock = Tribe__Tickets__Tickets::global_stock_available();
		$tickets           = Tribe__Tickets__Tickets::get_event_tickets( $post->ID );
		$global_stock      = new Tribe__Tickets__Global_Stock( $post->ID );

		include $this->path . 'src/admin-views/meta-box.php';
	}

	/**
	 * Render the ticket row into the ticket table
	 *
	 * @since 4.6
	 *
	 * @param Tribe__Tickets__Ticket_Object $ticket
	 */
	public function render_ticket_row( $ticket ) {
		$provider      = $ticket->provider_class;
		$provider_obj  = call_user_func( array( $provider, 'get_instance' ) );
		$inventory     = $ticket->inventory();
		$available     = $ticket->available();
		$capacity      = $ticket->capacity();
		$stock         = $ticket->stock();
		$needs_warning = false;
		$mode          = $ticket->global_stock_mode();
		$event         = $ticket->get_event();

		// If we don't have an event we should even continue
		if ( ! $event ) {
			return;
		}

		if (
			'Tribe__Tickets_Plus__Commerce__WooCommerce__Main' === $ticket->provider_class
			&& -1 !== $capacity
		) {
			$product = wc_get_product( $ticket->ID );
			$shared_stock = new Tribe__Tickets__Global_Stock( $event->ID );
			$needs_warning = (int) $inventory !== (int) $stock;

			// We remove the warning flag when shared stock is used
			if ( $shared_stock->is_enabled() && (int) $stock >= (int) $shared_stock->get_stock_level() ) {
				$needs_warning = false;
			}
		}

		?>
		<tr class="<?php echo esc_attr( $provider ); ?> is-expanded" data-ticket-order-id="order_<?php echo esc_attr( $ticket->ID ); ?>" data-ticket-type-id="<?php echo esc_attr( $ticket->ID ); ?>">
			<td class="column-primary ticket_name <?php echo esc_attr( $provider ); ?>" data-label="<?php esc_html_e( 'Ticket Type:', 'event-tickets' ); ?>">
				<span class="dashicons dashicons-screenoptions tribe-handle"></span>
				<input
					type="hidden"
					class="tribe-ticket-field-order"
					name="tribe-tickets[<?php echo esc_attr( $ticket->ID ); ?>][order]"
					value="<?php echo esc_attr( $ticket->menu_order ); ?>"
					<?php echo 'Tribe__Tickets__RSVP' === $ticket->provider_class ? 'disabled' : ''; ?>
				>
				<?php echo esc_html( $ticket->name ); ?>
			</td>

			<?php
			/**
			 * Allows for the insertion of additional content into the main ticket admin panel after the tickets listing
			 *
			 * @since 4.6
			 *
			 * @param Tribe__Tickets__Ticket_Object $ticket
			 * @param obj ecommerce provider object
			 */
			do_action( 'tribe_events_tickets_ticket_table_add_tbody_column', $ticket, $provider_obj );
			?>

			<td class="ticket_capacity">
				<span class='tribe-mobile-only'><?php esc_html_e( 'Capacity:', 'event-tickets' ); ?></span>
				<?php tribe_tickets_get_readable_amount( $capacity, $mode, true ); ?>
			</td>

			<td class="ticket_available">
				<span class='tribe-mobile-only'><?php esc_html_e( 'Available:', 'event-tickets' ); ?></span>
				<?php if ( $needs_warning ) : ?>
					<span class="dashicons dashicons-warning required" title="<?php esc_attr_e( 'The number of Complete ticket sales does not match the number of attendees. Please check the Attendees list and adjust ticket stock in WooCommerce as needed.', 'event-tickets' ) ?>"></span>
				<?php endif; ?>

				<?php tribe_tickets_get_readable_amount( $available, $mode, true ); ?>
			</td>

			<td class="ticket_edit">
				<?php
				printf(
					"<button data-provider='%s' data-ticket-id='%s' title='%s' class='ticket_edit_button'><span class='ticket_edit_text'>%s</span></a>",
					esc_attr( $ticket->provider_class ),
					esc_attr( $ticket->ID ),
					esc_attr( sprintf( __( '( Ticket ID: %d )', 'tribe-tickets' ), $ticket->ID ) ),
					esc_html( $ticket->name )
				);
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Echoes the markup for the tickets list in the tickets metabox
	 *
	 * @param int $unused_post_id event ID
	 * @param array $tickets
	 */
	public function ticket_list_markup( $unused_post_id, $tickets = array() ) {
		if ( ! empty( $tickets ) ) {
			include $this->path . 'src/admin-views/list.php';
		}
	}

	/**
	 * Returns the markup for the tickets list in the tickets metabox
	 *
	 * @param array $tickets
	 *
	 * @return string
	 */
	public function get_ticket_list_markup( $tickets = array() ) {
		ob_start();
		$this->ticket_list_markup( null, $tickets );
		$return = ob_get_clean();

		return $return;
	}

	/**
	 * Returns the markup for the Settings Panel for Tickets
	 *
	 * @param  int    $post_id
	 *
	 * @return string
	 */
	public function get_settings_panel( $post_id ) {
		ob_start();
		include $this->path . 'src/admin-views/settings_admin_panel.php';
		$return = ob_get_clean();

		return $return;
	}

	/**
	 * Returns the markup for the History for a Given Ticket
	 *
	 * @param  int    $ticket_id
	 *
	 * @return string
	 */
	public function get_history_content( $post_id, $ticket ) {
		ob_start();
		include $this->path . 'src/admin-views/tickets-history.php';
		$return = ob_get_clean();

		return $return;
	}

	/**
	 * Returns the attachment ID for the header image for a event.
	 *
	 * @param $event_id
	 *
	 * @return mixed
	 */
	public function get_header_image_id( $event_id ) {
		return get_post_meta( $event_id, $this->image_header_field, true );
	}

	/**
	 * Save or delete the image header for tickets on an event
	 *
	 * @param int $post_id
	 */
	public function save_image_header( $post_id ) {
		if ( ! ( isset( $_POST[ 'tribe-tickets-post-settings' ] ) && wp_verify_nonce( $_POST[ 'tribe-tickets-post-settings' ], 'tribe-tickets-meta-box' ) ) ) {
			return;
		}

		// don't do anything on autosave or auto-draft either or massupdates
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( empty( $_POST['tribe_ticket_header_image_id'] ) ) {
			delete_post_meta( $post_id, $this->image_header_field );
		} else {
			update_post_meta( $post_id, $this->image_header_field, $_POST['tribe_ticket_header_image_id'] );
		}

		return;
	}

	/**
	 * Save the the drag-n-drop ticket order
	 *
	 * @since 4.6
	 *
	 * @param int $post
	 *
	 */
	public function save_order( $post, $tickets = null ) {
		// We're calling this during post save, so the save nonce has already been checked.

		// don't do anything on autosave, auto-draft, or massupdates
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return false;
		}

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post );
		}

		// Bail on Invalid post
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// If we didn't get any Ticket data we fetch from the $_POST
		if ( is_null( $tickets ) ) {
			$tickets = Tribe__Utils__Array::get( $_POST, array( 'tribe-tickets' ), null );
		}

		if ( empty( $tickets ) ) {
			return false;
		}

		foreach ( $tickets as $id => $ticket ) {
			if ( ! isset( $ticket['order'] ) ) {
				continue;
			}

			$args = array(
				'ID'         => absint( $id ),
				'menu_order' => (int) $ticket['order'],
			);

			$updated[] = wp_update_post( $args );
		}

		// Verify if any failed
		return ! in_array( 0, $updated );
	}

	protected function sort_by_menu_order( $a, $b ) {
		return $a->menu_order - $b->menu_order;
	}

	/**
	 * Sorts tickets according to stored menu_order
	 *
	 * @since 4.6
	 *
	 * @param array $tickets array of ticket objects
	 *
	 * @return array - sorted array of ticket objects
	 */
	public function sort_tickets_by_menu_order( $tickets ) {
		foreach ( $tickets as $key => $ticket ) {
			// make sure they are ordered correctly
			$orderpost          = get_post( $ticket->ID );
			$ticket->menu_order = $orderpost->menu_order;
		}

		usort( $tickets, array( $this, 'sort_by_menu_order' ) );

		return $tickets;
	}

	/**
	 * Saves the event ticket settings via ajax
	 *
	 * @since 4.6
	 */
	public function ajax_handler_save_settings() {
		$params = array();
		$id = absint( $_POST['post_ID'] );
		$params = wp_parse_args( $_POST['formdata'], $params );

		/**
		 * Allow other plugins to hook into this to add settings
		 *
		 * @since 4.6
		 *
		 * @param array $params the array of parameters to filter
		 */
		do_action( 'tribe_events_save_tickets_settings', $params );

		if ( ! empty( $params['tribe_ticket_header_image_id'] ) ) {
			update_post_meta( $id, $this->image_header_field, $params['tribe_ticket_header_image_id'] );
		} else {
			delete_post_meta( $id, $this->image_header_field );
		}

		// We reversed this logic on the back end
		if ( class_exists( 'Tribe__Tickets_Plus__Attendees_List' ) ) {
			update_post_meta( $id, Tribe__Tickets_Plus__Attendees_List::HIDE_META_KEY, ! empty( $params['tribe_show_attendees'] ) );
		}

		// Change the default ticket provider
		if ( ! empty( $params['default_ticket_provider'] ) ) {
			update_post_meta( $id, $this->key_provider_field, $params['default_ticket_provider'] );
		} else {
			delete_post_meta( $id, $this->key_provider_field );
		}

		wp_send_json_success( $params );
	}

	/**
	 * Static Singleton Factory Method
	 *
	 * @return Tribe__Tickets__Tickets_Handler
	 */
	public static function instance() {
		return tribe( 'tickets.handler' );
	}

	/************************
	 *                      *
	 *  Deprecated Methods  *
	 *                      *
	 ************************/

	/**
	 * Slug of the admin page for attendees
	 *
	 * @deprecated TBD
	 *
	 * @var string
	 */
	public static $attendees_slug = 'tickets-attendees';

	/**
	 * Whether the ticket handler should render the title in the attendees report.
	 *
	 * @deprecated TBD
	 *
	 * @param bool $should_render_title
	 */
	public function should_render_title( $deprecated ) {
		_deprecated_function( __METHOD__, 'TBD', 'add_filter( \'tribe_tickets_attendees_show_title\', \'_return_false\' );' );
	}

	/**
	 * Returns the current post being handled.
	 *
	 * @deprecated TBD
	 *
	 * @return array|bool|null|WP_Post
	 */
	public function get_post() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::get_post' );
	}

	/**
	 * Print Check In Totals at top of Column
	 *
	 * @deprecated TBD
	 *
	 */
	public function print_checkedin_totals() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::print_checkedin_totals' );
	}

	/**
	 * Returns the full URL to the attendees report page.
	 *
	 * @deprecated TBD
	 *
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function get_attendee_report_link( $post ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::get_report_link' );
	}

	/**
	 * Adds the "attendees" link in the admin list row actions for each event.
	 *
	 * @deprecated TBD
	 *
	 * @param $actions
	 *
	 * @return array
	 */
	public function attendees_row_action( $actions ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::filter_admin_row_actions' );
	}

	/**
	 * Registers the Attendees admin page
	 */
	public function attendees_page_register() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::register_page' );
	}

	/**
	 * Enqueues the JS and CSS for the attendees page in the admin
	 *
	 * @deprecated TBD
	 *
	 * @param $hook
	 */
	public function attendees_page_load_css_js( $hook ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::enqueue_assets' );
	}

	/**
	 * Loads the WP-Pointer for the Attendees screen
	 *
	 * @deprecated TBD
	 *
	 * @param $hook
	 */
	public function attendees_page_load_pointers( $hook ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::load_pointers' );
	}

	/**
	 * Sets up the Attendees screen data.
	 *
	 * @deprecated TBD
	 *
	 */
	public function attendees_page_screen_setup() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::screen_setup' );
	}

	/**
	 * @deprecated TBD
	 */
	public function attendees_admin_body_class( $body_classes ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::filter_admin_body_class' );
	}

	/**
	 * Sets the browser title for the Attendees admin page.
	 * Uses the event title.
	 *
	 * @deprecated TBD
	 *
	 * @param $admin_title
	 * @param $unused_title
	 *
	 * @return string
	 */
	public function attendees_admin_title( $admin_title, $unused_title ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::filter_admin_title' );
	}

	/**
	 * Renders the Attendees page
	 *
	 * @deprecated TBD
	 */
	public function attendees_page_inside() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::render' );
	}

	/**
	 * Generates a list of attendees taking into account the Screen Options.
	 * It's used both for the Email functionality, as for the CSV export.
	 *
	 * @deprecated TBD
	 *
	 * @param $event_id
	 *
	 * @return array
	 */
	private function generate_filtered_attendees_list( $event_id ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::generate_filtered_list' );
	}

	/**
	 * Checks if the user requested a CSV export from the attendees list.
	 * If so, generates the download and finishes the execution.
	 *
	 * @deprecated TBD
	 */
	public function maybe_generate_attendees_csv() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::maybe_generate_csv' );
	}

	/**
	 * Handles the "send to email" action for the attendees list.
	 *
	 * @deprecated TBD
	 */
	public function send_attendee_mail_list() {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::send_mail_list' );
	}

	/**
	 * Sets the content type for the attendees to email functionality.
	 * Allows for sending an HTML email.
	 *
	 * @deprecated TBD
	 *
	 * @param $content_type
	 *
	 * @return string
	 */
	public function set_contenttype( $content_type ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::set_contenttype' );
	}

	/**
	 * Tests if the user has the specified capability in relation to whatever post type
	 * the ticket relates to.
	 *
	 * For example, if tickets are created for the banana post type, the generic capability
	 * "edit_posts" will be mapped to "edit_bananas" or whatever is appropriate.
	 *
	 * @deprecated TBD
	 *
	 * @internal for internal plugin use only (in spite of having public visibility)
	 *
	 * @param  string $generic_cap
	 * @param  int    $event_id
	 * @return boolean
	 */
	public function user_can( $generic_cap, $event_id ) {
		_deprecated_function( __METHOD__, 'TBD', 'Tribe__Tickets__Attendees::user_can' );
	}
}

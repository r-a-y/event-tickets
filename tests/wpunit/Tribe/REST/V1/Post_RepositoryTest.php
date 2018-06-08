<?php

namespace Tribe\Tickets\REST\V1;

use Tribe\Tickets\Test\Testcases\Ticket_TestCase;
use Tribe__Tickets__Main as Main;
use Tribe__Tickets__REST__V1__Messages as Messages;
use Tribe__Tickets__REST__V1__Post_Repository as Post_Repository;

class Post_RepositoryTest extends Ticket_TestCase {

	/**
	 * @var \Tribe__REST__Messages_Interface
	 */
	protected $messages;

	public function setUp() {
		// before
		parent::setUp();

		// your set up methods here
		$this->messages = new Messages();
	}

	/**
	 * @test
	 * it should be instantiatable
	 */
	public function it_should_be_instantiatable() {
		$sut = $this->make_instance();

		$this->assertInstanceOf( Post_Repository::class, $sut );
	}

	/**
	 * @test
	 * it should return a WP_Error when trying to get ticket data for non existing post
	 */
	public function it_should_return_a_wp_error_when_trying_to_get_ticket_data_for_non_existing_post() {
		$sut = $this->make_instance();

		$data = $sut->get_attendee_data( 22131 );

		/** @var \WP_Error $data */
		$this->assertWPError( $data );
		$this->assertEquals( $this->messages->get_message( 'ticket-not-found' ), $data->get_error_message() );
	}

	/**
	 * @test
	 * it should return a WP_Error when trying to get ticket data for non ticket
	 */
	public function it_should_return_a_wp_error_when_trying_to_get_ticket_data_for_non_ticket() {
		$sut = $this->make_instance();

		$data = $sut->get_attendee_data( $this->factory()->post->create() );

		/** @var \WP_Error $data */
		$this->assertWPError( $data );
		$this->assertEquals( $this->messages->get_message( 'ticket-not-found' ), $data->get_error_message() );
	}

	/**
	 * @test
	 * it should return an ticket array representation if ticket
	 */
	public function it_should_return_an_ticket_array_representation_if_ticket() {
		$ticket = $this->factory()->rsvp_attendee->create();

		$sut  = $this->make_instance();
		$data = $sut->get_attendee_data( $ticket );

		$this->assertInternalType( 'array', $data );
	}

	/**
	 * @test
	 * it should reutrn the array representation of an ticket if trying to get an ticket data
	 */
	public function it_should_return_the_array_representation_of_an_ticket_if_trying_to_get_an_ticket_data() {
		$ticket = $this->factory()->rsvp_attendee->create();

		$sut  = $this->make_instance();
		$data = $sut->get_data( $ticket );

		$this->assertInternalType( 'array', $data );
		$this->assertEquals( $ticket, $data['id'] );
	}

	/**
	 * @test
	 * it should return an ticket data if trying to get data for an ticket
	 */
	public function it_should_return_an_ticket_data_if_trying_to_get_data_for_an_ticket() {
		$ticket = $this->factory()->rsvp_attendee->create();

		$sut  = $this->make_instance();
		$data = $sut->get_data( $ticket );

		$this->assertInternalType( 'array', $data );
		$this->assertEquals( $ticket, $data['id'] );
	}

	/**
	 * @return Post_Repository
	 */
	private function make_instance() {
		return new Post_Repository( $this->messages );
	}
}
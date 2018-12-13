/**
 * External dependencies
 */
import React from 'react';
import PropTypes from 'prop-types';
import classNames from 'classnames';

/**
 * Wordpress dependencies
 */
import { InnerBlocks } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Availability from '../availability/container';
import { InactiveBlock } from '@moderntribe/tickets/elements';
import { LAYOUT } from '@moderntribe/tickets/elements/inactive-block/element';
import { TicketInactive } from '@moderntribe/tickets/icons';
import './style.pcss';

const TicketsOverlay = () => <div className="tribe-editor__tickets__overlay" />;

const TicketsContainer = ( {
	hasOverlay,
	hasProviders,
	showAvailability,
	showInactiveBlock,
} ) => {
	const messages = {
		title: hasProviders
			? __( 'There are no tickets yet', 'event-tickets' )
			: __( 'There is no ecommerce available', 'event-tickets' ),
		description: hasProviders
			? __( 'Edit this block to create your first ticket.', 'event-tickets' )
			: __( 'To create tickets, you\'ll need to enable an ecommerce solution.', 'event-tickets' ),
	};

	const innerBlocksClassName = classNames( {
		'tribe-editor__tickets__inner-blocks': true,
		'tribe-editor__tickets__inner-blocks--show': ! showInactiveBlock,
	} );

	return (
		<div className="tribe-editor__tickets__container">
			<div className={ innerBlocksClassName }>
				<InnerBlocks
					allowedBlocks={ [ 'tribe/tickets-item' ] }
					templateLock="insert"
				/>
			</div>
			{
				showInactiveBlock && (
					<InactiveBlock
						layout={ LAYOUT.ticket }
						title={ messages.title }
						description={ messages.description }
						icon={ <TicketInactive /> }
					/>
				)
			}
			{ showAvailability && <Availability /> }
			{ hasOverlay && <TicketsOverlay /> }
		</div>
	);
};

TicketsContainer.propTypes = {
	hasOverlay: PropTypes.bool,
	hasProviders: PropTypes.bool,
	showAvailability: PropTypes.bool,
	showInactiveBlock: PropTypes.bool,
};

export default TicketsContainer;

<?php
/**
 * Plugin Name: Event Tickets
 * Plugin URI:  http://m.tri.be/1acb
 * Description: Event Tickets allows you to sell basic tickets and collect RSVPs from any post, page, or event.
 * Version:     4.10
 * Author:      Modern Tribe, Inc.
 * Author URI:  http://m.tri.be/28
 * Text Domain: event-tickets
 * Domain Path: /lang/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 Copyright 2010-2012 by Modern Tribe Inc and the contributors

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'EVENT_TICKETS_DIR', dirname( __FILE__ ) );
define( 'EVENT_TICKETS_MAIN_PLUGIN_FILE', __FILE__ );

// the main plugin class
require_once EVENT_TICKETS_DIR . '/src/Tribe/Main.php';

Tribe__Tickets__Main::instance();

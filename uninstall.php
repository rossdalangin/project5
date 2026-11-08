<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up plugin options and database tables according to WordPress VIP standards.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete custom database table
$table_name = $wpdb->prefix . 'appointment_slots';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange

// Delete stored options
$options_to_delete = array(
	'ross_booking_business_name',
	'ross_booking_admin_email',
	'ross_booking_currency_symbol',
	'ross_booking_slot_interval',
	'ross_booking_auto_approve',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

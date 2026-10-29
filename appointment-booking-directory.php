<?php
/**
 * Plugin Name: Appointment Booking & Service Directory
 * Description: A self-contained WordPress appointment booking and service directory plugin without external SaaS dependencies.
 * Version: 1.0.0
 * Author: Senior WordPress Full-Stack Engineer
 * Text Domain: appointment-booking-directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ROSS_BOOKING_VERSION', '1.0.0' );
define( 'ROSS_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'ROSS_BOOKING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation Hook: Create Custom Database Table
 */
function ross_booking_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'appointment_slots';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		service_id varchar(50) NOT NULL,
		booking_date date NOT NULL,
		time_slot varchar(50) NOT NULL,
		client_name varchar(100) NOT NULL,
		client_email varchar(100) NOT NULL,
		status varchar(20) DEFAULT 'pending' NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'ross_booking_activate' );

/**
 * Default Available Time Slots Generator
 */
function ross_get_default_time_slots() {
	return array(
		'09:00 AM',
		'10:00 AM',
		'11:00 AM',
		'01:00 PM',
		'02:00 PM',
		'03:00 PM',
		'04:00 PM',
	);
}

/**
 * Default Services List
 */
function ross_get_services() {
	return array(
		'service-1' => array(
			'id'          => 'service-1',
			'name'        => 'General Consultation',
			'description' => 'A 30-minute introductory consultation session.',
		),
		'service-2' => array(
			'id'          => 'service-2',
			'name'        => 'Full Technical Audit',
			'description' => 'A comprehensive review and audit of your system architecture.',
		),
		'service-3' => array(
			'id'          => 'service-3',
			'name'        => 'Custom Strategy Session',
			'description' => 'One-on-one deep dive strategy session tailored to your needs.',
		),
	);
}

/**
 * Enqueue Frontend Scripts and Styles
 */
function ross_booking_enqueue_scripts() {
	wp_enqueue_style( 'ross-booking-css', ROSS_BOOKING_URL . 'assets/css/frontend.css', array(), ROSS_BOOKING_VERSION );
	wp_enqueue_script( 'ross-booking-js', ROSS_BOOKING_URL . 'assets/js/frontend.js', array(), ROSS_BOOKING_VERSION, true );

	wp_localize_script( 'ross-booking-js', 'rossBookingData', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'ross_booking_nonce' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'ross_booking_enqueue_scripts' );

/**
 * Shortcode Output
 */
function ross_booking_shortcode( $atts ) {
	ross_booking_enqueue_scripts();
	$services = ross_get_services();

	ob_start();
	?>
	<div class="ross-booking-container">
		<h2><?php esc_html_e( 'Book an Appointment', 'appointment-booking-directory' ); ?></h2>

		<div class="ross-form-group">
			<label for="ross-service-select"><?php esc_html_e( 'Select Service', 'appointment-booking-directory' ); ?></label>
			<select id="ross-service-select" class="ross-input">
				<option value=""><?php esc_html_e( '-- Choose a Service --', 'appointment-booking-directory' ); ?></option>
				<?php foreach ( $services as $service ) : ?>
					<option value="<?php echo esc_attr( $service['id'] ); ?>">
						<?php echo esc_html( $service['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ross-form-group">
			<label for="ross-booking-date"><?php esc_html_e( 'Select Date', 'appointment-booking-directory' ); ?></label>
			<input type="date" id="ross-booking-date" class="ross-input" />
		</div>

		<div id="ross-slots-container" style="display:none; margin-top: 20px;">
			<h3><?php esc_html_e( 'Available Time Slots', 'appointment-booking-directory' ); ?></h3>
			<div id="ross-slots-list"></div>
		</div>

		<form id="ross-booking-form" style="display:none; margin-top: 20px;">
			<h3><?php esc_html_e( 'Your Details', 'appointment-booking-directory' ); ?></h3>
			<input type="hidden" id="ross-selected-slot" name="time_slot" />

			<div class="ross-form-group">
				<label for="ross-client-name"><?php esc_html_e( 'Full Name', 'appointment-booking-directory' ); ?></label>
				<input type="text" id="ross-client-name" required />
			</div>

			<div class="ross-form-group">
				<label for="ross-client-email"><?php esc_html_e( 'Email Address', 'appointment-booking-directory' ); ?></label>
				<input type="email" id="ross-client-email" required />
			</div>

			<button type="submit" class="ross-btn-submit"><?php esc_html_e( 'Confirm Booking', 'appointment-booking-directory' ); ?></button>
		</form>

		<div id="ross-booking-message" style="display:none;"></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'appointment_booking', 'ross_booking_shortcode' );

/**
 * Helper: Validate Date (YYYY-MM-DD)
 */
function ross_is_valid_date( $date_string ) {
	$d = DateTime::createFromFormat( 'Y-m-d', $date_string );
	return $d && $d->format( 'Y-m-d' ) === $date_string;
}

/**
 * AJAX Callback: Get Available Slots
 */
function ross_get_available_slots() {
	check_ajax_referer( 'ross_booking_nonce', 'security' );

	$service_id   = isset( $_REQUEST['service_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['service_id'] ) ) : '';
	$booking_date = isset( $_REQUEST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['booking_date'] ) ) : '';

	if ( empty( $service_id ) || empty( $booking_date ) ) {
		wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'appointment-booking-directory' ) ) );
	}

	// Strict Service ID Validation
	$valid_services = array_keys( ross_get_services() );
	if ( ! in_array( $service_id, $valid_services, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid service selected.', 'appointment-booking-directory' ) ) );
	}

	// Strict Date Validation
	if ( ! ross_is_valid_date( $booking_date ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid date format provided.', 'appointment-booking-directory' ) ) );
	}

	$today = current_time( 'Y-m-d' );
	if ( $booking_date < $today ) {
		wp_send_json_error( array( 'message' => __( 'Booking date cannot be in the past.', 'appointment-booking-directory' ) ) );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'appointment_slots';

	// Query booked time slots for the given service and date (exclude cancelled)
	$query = $wpdb->prepare(
		"SELECT time_slot FROM {$table_name} WHERE service_id = %s AND booking_date = %s AND status != 'cancelled'",
		$service_id,
		$booking_date
	);

	$booked_results = $wpdb->get_col( $query );
	$booked_slots   = is_array( $booked_results ) ? $booked_results : array();

	$all_slots       = ross_get_default_time_slots();
	$available_slots = array();

	foreach ( $all_slots as $slot ) {
		$available_slots[] = array(
			'time_slot' => $slot,
			'available' => ! in_array( $slot, $booked_slots, true ),
		);
	}

	wp_send_json_success( array(
		'service_id'   => $service_id,
		'booking_date' => $booking_date,
		'slots'        => $available_slots,
	) );
}
add_action( 'wp_ajax_ross_get_available_slots', 'ross_get_available_slots' );
add_action( 'wp_ajax_nopriv_ross_get_available_slots', 'ross_get_available_slots' );

/**
 * AJAX Callback: Submit Booking
 */
function ross_submit_booking() {
	check_ajax_referer( 'ross_booking_nonce', 'security' );

	$service_id   = isset( $_POST['service_id'] ) ? sanitize_text_field( wp_unslash( $_POST['service_id'] ) ) : '';
	$booking_date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$time_slot    = isset( $_POST['time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['time_slot'] ) ) : '';
	$client_name  = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
	$client_email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';

	if ( empty( $service_id ) || empty( $booking_date ) || empty( $time_slot ) || empty( $client_name ) || empty( $client_email ) ) {
		wp_send_json_error( array( 'message' => __( 'All fields are required.', 'appointment-booking-directory' ) ) );
	}

	// Strict Service ID Validation
	$valid_services = array_keys( ross_get_services() );
	if ( ! in_array( $service_id, $valid_services, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid service selected.', 'appointment-booking-directory' ) ) );
	}

	// Strict Date Validation
	if ( ! ross_is_valid_date( $booking_date ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid date format provided.', 'appointment-booking-directory' ) ) );
	}

	$today = current_time( 'Y-m-d' );
	if ( $booking_date < $today ) {
		wp_send_json_error( array( 'message' => __( 'Booking date cannot be in the past.', 'appointment-booking-directory' ) ) );
	}

	// Strict Time Slot Validation
	$allowed_slots = ross_get_default_time_slots();
	if ( ! in_array( $time_slot, $allowed_slots, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid time slot selected.', 'appointment-booking-directory' ) ) );
	}

	if ( ! is_email( $client_email ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid email address provided.', 'appointment-booking-directory' ) ) );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'appointment_slots';

	// Check if slot is already booked for this service and date
	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE service_id = %s AND booking_date = %s AND time_slot = %s AND status != 'cancelled'",
			$service_id,
			$booking_date,
			$time_slot
		)
	);

	if ( $existing > 0 ) {
		wp_send_json_error( array( 'message' => __( 'Selected time slot is no longer available.', 'appointment-booking-directory' ) ) );
	}

	$inserted = $wpdb->insert(
		$table_name,
		array(
			'service_id'   => $service_id,
			'booking_date' => $booking_date,
			'time_slot'    => $time_slot,
			'client_name'  => $client_name,
			'client_email' => $client_email,
			'status'       => 'pending',
			'created_at'   => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		wp_send_json_error( array( 'message' => __( 'Failed to record booking. Please try again.', 'appointment-booking-directory' ) ) );
	}

	wp_send_json_success( array(
		'booking_id' => $wpdb->insert_id,
		'message'    => __( 'Booking submitted successfully!', 'appointment-booking-directory' ),
	) );
}
add_action( 'wp_ajax_ross_submit_booking', 'ross_submit_booking' );
add_action( 'wp_ajax_nopriv_ross_submit_booking', 'ross_submit_booking' );

/**
 * Register Admin Menu and Submenu Page
 */
function ross_admin_menu() {
	add_menu_page(
		__( 'Appointment Booking', 'appointment-booking-directory' ),
		__( 'Appointments', 'appointment-booking-directory' ),
		'manage_options',
		'ross-appointments',
		'ross_admin_appointments_page',
		'dashicons-calendar-alt',
		25
	);

	add_submenu_page(
		'ross-appointments',
		__( 'Manage Bookings', 'appointment-booking-directory' ),
		__( 'Manage Bookings', 'appointment-booking-directory' ),
		'manage_options',
		'ross-appointments',
		'ross_admin_appointments_page'
	);
}
add_action( 'admin_menu', 'ross_admin_menu' );

/**
 * Admin Dashboard Page Callback
 */
function ross_admin_appointments_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'appointment-booking-directory' ) );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'appointment_slots';
	$notice     = '';

	// Handle Admin Actions (Approve / Cancel)
	if ( isset( $_GET['action'], $_GET['booking_id'] ) ) {
		$action     = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$booking_id = intval( $_GET['booking_id'] );

		if ( check_admin_referer( 'ross_admin_action_' . $booking_id ) ) {
			if ( 'approve' === $action ) {
				$updated = $wpdb->update(
					$table_name,
					array( 'status' => 'approved' ),
					array( 'id' => $booking_id ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false !== $updated ) {
					$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Booking approved successfully.', 'appointment-booking-directory' ) . '</p></div>';
				}
			} elseif ( 'cancel' === $action ) {
				$updated = $wpdb->update(
					$table_name,
					array( 'status' => 'cancelled' ),
					array( 'id' => $booking_id ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false !== $updated ) {
					$notice = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Booking cancelled successfully.', 'appointment-booking-directory' ) . '</p></div>';
				}
			}
		}
	}

	// Fetch bookings
	$bookings = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC" );
	$services = ross_get_services();

	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Appointment Bookings Directory', 'appointment-booking-directory' ); ?></h1>
		<hr class="wp-header-end">

		<?php echo wp_kses_post( $notice ); ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" style="width: 60px;"><?php esc_html_e( 'ID', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Service', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Booking Date', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Time Slot', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Client Name', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Client Email', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'appointment-booking-directory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'appointment-booking-directory' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $bookings ) ) : ?>
					<?php foreach ( $bookings as $item ) : ?>
						<?php
						$service_name = isset( $services[ $item->service_id ] ) ? $services[ $item->service_id ]['name'] : $item->service_id;
						$approve_url  = wp_nonce_url(
							add_query_arg(
								array(
									'page'       => 'ross-appointments',
									'action'     => 'approve',
									'booking_id' => $item->id,
								),
								admin_url( 'admin.php' )
							),
							'ross_admin_action_' . $item->id
						);
						$cancel_url   = wp_nonce_url(
							add_query_arg(
								array(
									'page'       => 'ross-appointments',
									'action'     => 'cancel',
									'booking_id' => $item->id,
								),
								admin_url( 'admin.php' )
							),
							'ross_admin_action_' . $item->id
						);
						?>
						<tr>
							<td><?php echo esc_html( $item->id ); ?></td>
							<td><strong><?php echo esc_html( $service_name ); ?></strong></td>
							<td><?php echo esc_html( $item->booking_date ); ?></td>
							<td><?php echo esc_html( $item->time_slot ); ?></td>
							<td><?php echo esc_html( $item->client_name ); ?></td>
							<td><a href="<?php echo esc_url( 'mailto:' . $item->client_email ); ?>"><?php echo esc_html( $item->client_email ); ?></a></td>
							<td>
								<?php if ( 'approved' === $item->status ) : ?>
									<span class="badge" style="background:#c6e1c6; color:#234a23; padding:3px 8px; border-radius:3px; font-weight:bold;"><?php esc_html_e( 'Approved', 'appointment-booking-directory' ); ?></span>
								<?php elseif ( 'cancelled' === $item->status ) : ?>
									<span class="badge" style="background:#f8d7da; color:#721c24; padding:3px 8px; border-radius:3px; font-weight:bold;"><?php esc_html_e( 'Cancelled', 'appointment-booking-directory' ); ?></span>
								<?php else : ?>
									<span class="badge" style="background:#fff3cd; color:#856404; padding:3px 8px; border-radius:3px; font-weight:bold;"><?php esc_html_e( 'Pending', 'appointment-booking-directory' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'approved' !== $item->status ) : ?>
									<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'appointment-booking-directory' ); ?></a>
								<?php endif; ?>
								<?php if ( 'cancelled' !== $item->status ) : ?>
									<a href="<?php echo esc_url( $cancel_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to cancel this booking?');"><?php esc_html_e( 'Cancel', 'appointment-booking-directory' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="8"><?php esc_html_e( 'No appointments booked yet.', 'appointment-booking-directory' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

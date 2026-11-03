<?php
/**
 * Plugin Name: Appointment Booking & Service Directory Pro
 * Description: A premium, self-contained WordPress appointment booking and service directory plugin with automated notifications, admin settings, analytics, filtering, and CSV export without external SaaS dependencies.
 * Version: 1.3.0
 * Author: Senior WordPress Full-Stack Engineer
 * Text Domain: appointment-booking-directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ROSS_BOOKING_VERSION', '1.3.0' );
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

	// Initialize default options
	if ( false === get_option( 'ross_booking_business_name' ) ) {
		update_option( 'ross_booking_business_name', get_bloginfo( 'name' ) );
	}
	if ( false === get_option( 'ross_booking_admin_email' ) ) {
		update_option( 'ross_booking_admin_email', get_option( 'admin_email' ) );
	}
	if ( false === get_option( 'ross_booking_currency_symbol' ) ) {
		update_option( 'ross_booking_currency_symbol', '$' );
	}
	if ( false === get_option( 'ross_booking_slot_interval' ) ) {
		update_option( 'ross_booking_slot_interval', '60' );
	}
	if ( false === get_option( 'ross_booking_auto_approve' ) ) {
		update_option( 'ross_booking_auto_approve', '0' );
	}
}
register_activation_hook( __FILE__, 'ross_booking_activate' );

/**
 * Get Configured Time Slots Based on Settings
 */
function ross_get_default_time_slots() {
	$interval = get_option( 'ross_booking_slot_interval', '60' );

	if ( '30' === $interval ) {
		return array(
			'09:00 AM',
			'09:30 AM',
			'10:00 AM',
			'10:30 AM',
			'11:00 AM',
			'11:30 AM',
			'01:00 PM',
			'01:30 PM',
			'02:00 PM',
			'02:30 PM',
			'03:00 PM',
			'03:30 PM',
			'04:00 PM',
			'04:30 PM',
		);
	}

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
 * Premium Services Directory List with Metadata
 */
function ross_get_services() {
	$curr = get_option( 'ross_booking_currency_symbol', '$' );
	return array(
		'service-1' => array(
			'id'          => 'service-1',
			'name'        => 'General Consultation',
			'category'    => 'Consulting',
			'price'       => $curr . '50',
			'duration'    => '30 min',
			'description' => 'A 30-minute introductory consultation session.',
		),
		'service-2' => array(
			'id'          => 'service-2',
			'name'        => 'Full Technical Audit',
			'category'    => 'Engineering',
			'price'       => $curr . '250',
			'duration'    => '90 min',
			'description' => 'A comprehensive review and audit of your system architecture.',
		),
		'service-3' => array(
			'id'          => 'service-3',
			'name'        => 'Custom Strategy Session',
			'category'    => 'Strategy',
			'price'       => $curr . '150',
			'duration'    => '60 min',
			'description' => 'One-on-one deep dive strategy session tailored to your needs.',
		),
	);
}

/**
 * Get Client IP Address Securely
 */
function ross_get_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
}

/**
 * Email Notification Dispatcher (Protected against header injection)
 */
function ross_send_booking_email( $type, $booking_id, $client_email, $client_name, $service_name, $booking_date, $time_slot ) {
	$business_name = get_option( 'ross_booking_business_name', get_bloginfo( 'name' ) );
	$headers       = array( 'Content-Type: text/html; charset=UTF-8' );

	// Strip CR and LF characters to prevent email header injection
	$clean_email = str_replace( array( "\r", "\n" ), '', sanitize_email( $client_email ) );
	$clean_name  = str_replace( array( "\r", "\n" ), '', sanitize_text_field( $client_name ) );

	if ( 'new_booking' === $type ) {
		$subject = sprintf( '[%s] Appointment Request Received (#%d)', $business_name, $booking_id );
		$message = sprintf(
			'<h3>Hello %s,</h3><p>Thank you for submitting your booking request! Here are your appointment details:</p><ul><li><strong>Booking ID:</strong> #%d</li><li><strong>Service:</strong> %s</li><li><strong>Date:</strong> %s</li><li><strong>Time Slot:</strong> %s</li><li><strong>Status:</strong> Pending Approval</li></ul><p>We will review your booking shortly.</p>',
			esc_html( $clean_name ),
			intval( $booking_id ),
			esc_html( $service_name ),
			esc_html( $booking_date ),
			esc_html( $time_slot )
		);
	} elseif ( 'approved' === $type ) {
		$subject = sprintf( '[%s] Appointment Approved (#%d)', $business_name, $booking_id );
		$message = sprintf(
			'<h3>Hello %s,</h3><p>Great news! Your appointment booking has been <strong>APPROVED</strong>.</p><ul><li><strong>Booking ID:</strong> #%d</li><li><strong>Service:</strong> %s</li><li><strong>Date:</strong> %s</li><li><strong>Time Slot:</strong> %s</li></ul><p>We look forward to meeting you!</p>',
			esc_html( $clean_name ),
			intval( $booking_id ),
			esc_html( $service_name ),
			esc_html( $booking_date ),
			esc_html( $time_slot )
		);
	} elseif ( 'cancelled' === $type ) {
		$subject = sprintf( '[%s] Appointment Status Update (#%d)', $business_name, $booking_id );
		$message = sprintf(
			'<h3>Hello %s,</h3><p>Your appointment booking (#%d) for <strong>%s</strong> on %s at %s has been cancelled.</p><p>If you have any questions, please contact us.</p>',
			esc_html( $clean_name ),
			intval( $booking_id ),
			esc_html( $service_name ),
			esc_html( $booking_date ),
			esc_html( $time_slot )
		);
	} else {
		return false;
	}

	return wp_mail( $clean_email, $subject, $message, $headers );
}

/**
 * CSV Export Handler for Admin
 */
function ross_export_bookings_csv() {
	if ( ! isset( $_GET['action'] ) || 'ross_export_csv' !== $_GET['action'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'appointment-booking-directory' ) );
	}

	check_admin_referer( 'ross_export_csv_nonce' );

	global $wpdb;
	$table_name = $wpdb->prefix . 'appointment_slots';
	$bookings   = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC" );
	$services   = ross_get_services();

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=appointment-bookings-' . date( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'ID', 'Service ID', 'Service Name', 'Booking Date', 'Time Slot', 'Client Name', 'Client Email', 'Status', 'Created At' ) );

	if ( ! empty( $bookings ) ) {
		foreach ( $bookings as $b ) {
			$service_name = isset( $services[ $b->service_id ] ) ? $services[ $b->service_id ]['name'] : $b->service_id;
			fputcsv( $output, array(
				$b->id,
				$b->service_id,
				$service_name,
				$b->booking_date,
				$b->time_slot,
				$b->client_name,
				$b->client_email,
				$b->status,
				$b->created_at,
			) );
		}
	}

	fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
	exit;
}
add_action( 'admin_init', 'ross_export_bookings_csv' );

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
 * Enqueue Admin Scripts and Styles
 */
function ross_booking_enqueue_admin_scripts( $hook ) {
	if ( false !== strpos( $hook, 'ross-appointments' ) ) {
		wp_enqueue_style( 'ross-booking-admin-css', ROSS_BOOKING_URL . 'assets/css/admin.css', array(), ROSS_BOOKING_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'ross_booking_enqueue_admin_scripts' );

/**
 * Shortcode Output with Usability & Accessibility Improvements
 */
function ross_booking_shortcode( $atts ) {
	ross_booking_enqueue_scripts();
	$services = ross_get_services();

	ob_start();
	?>
	<div class="ross-booking-container" role="region" aria-label="<?php esc_attr_e( 'Appointment Booking', 'appointment-booking-directory' ); ?>">
		<div class="ross-header">
			<h2><?php esc_html_e( 'Book an Appointment', 'appointment-booking-directory' ); ?></h2>
			<p class="ross-subtitle"><?php esc_html_e( 'Select your service, choose a date and time slot, and confirm your appointment in 3 easy steps.', 'appointment-booking-directory' ); ?></p>
		</div>

		<!-- Step Progress Indicator -->
		<div class="ross-steps" aria-label="<?php esc_attr_e( 'Booking Steps', 'appointment-booking-directory' ); ?>">
			<div class="ross-step active" id="ross-step-1-badge">
				<span class="ross-step-num">1</span>
				<span class="ross-step-title"><?php esc_html_e( 'Service & Date', 'appointment-booking-directory' ); ?></span>
			</div>
			<div class="ross-step" id="ross-step-2-badge">
				<span class="ross-step-num">2</span>
				<span class="ross-step-title"><?php esc_html_e( 'Select Time', 'appointment-booking-directory' ); ?></span>
			</div>
			<div class="ross-step" id="ross-step-3-badge">
				<span class="ross-step-num">3</span>
				<span class="ross-step-title"><?php esc_html_e( 'Confirmation', 'appointment-booking-directory' ); ?></span>
			</div>
		</div>

		<div class="ross-form-group">
			<label for="ross-service-select"><?php esc_html_e( 'Step 1: Select Service', 'appointment-booking-directory' ); ?></label>
			<select id="ross-service-select" class="ross-input" aria-required="true">
				<option value=""><?php esc_html_e( '-- Choose a Service --', 'appointment-booking-directory' ); ?></option>
				<?php foreach ( $services as $service ) : ?>
					<option value="<?php echo esc_attr( $service['id'] ); ?>" data-price="<?php echo esc_attr( $service['price'] ); ?>" data-duration="<?php echo esc_attr( $service['duration'] ); ?>">
						<?php echo esc_html( $service['name'] ); ?> (<?php echo esc_html( $service['duration'] ); ?> - <?php echo esc_html( $service['price'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ross-form-group">
			<label for="ross-booking-date"><?php esc_html_e( 'Select Date', 'appointment-booking-directory' ); ?></label>
			<div class="ross-date-quick-actions">
				<button type="button" class="ross-quick-date-btn" data-days="0"><?php esc_html_e( 'Today', 'appointment-booking-directory' ); ?></button>
				<button type="button" class="ross-quick-date-btn" data-days="1"><?php esc_html_e( 'Tomorrow', 'appointment-booking-directory' ); ?></button>
			</div>
			<input type="date" id="ross-booking-date" class="ross-input" aria-required="true" />
		</div>

		<div id="ross-slots-container" style="display:none; margin-top: 25px;" aria-live="polite">
			<h3><?php esc_html_e( 'Step 2: Choose Available Time Slot', 'appointment-booking-directory' ); ?></h3>
			<div id="ross-slots-list" role="group" aria-label="<?php esc_attr_e( 'Time Slots', 'appointment-booking-directory' ); ?>"></div>
		</div>

		<form id="ross-booking-form" style="display:none; margin-top: 25px;">
			<h3><?php esc_html_e( 'Step 3: Enter Your Details', 'appointment-booking-directory' ); ?></h3>
			<input type="hidden" id="ross-selected-slot" name="time_slot" />

			<!-- Honeypot field for bot protection -->
			<div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true">
				<label for="ross-hp-field">Leave this empty</label>
				<input type="text" id="ross-hp-field" name="ross_hp" tabindex="-1" autocomplete="off" />
			</div>

			<div class="ross-form-group">
				<label for="ross-client-name"><?php esc_html_e( 'Full Name', 'appointment-booking-directory' ); ?></label>
				<input type="text" id="ross-client-name" placeholder="John Doe" maxlength="100" required />
			</div>

			<div class="ross-form-group">
				<label for="ross-client-email"><?php esc_html_e( 'Email Address', 'appointment-booking-directory' ); ?></label>
				<input type="email" id="ross-client-email" placeholder="john@example.com" maxlength="100" required />
			</div>

			<button type="submit" class="ross-btn-submit"><?php esc_html_e( 'Confirm Appointment Booking', 'appointment-booking-directory' ); ?></button>
		</form>

		<div id="ross-booking-message" style="display:none;" aria-live="polite"></div>
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

	// Honeypot check for spam bots
	if ( ! empty( $_POST['ross_hp'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Spam detection triggered.', 'appointment-booking-directory' ) ) );
	}

	// IP Rate Limiting (Max 5 booking submissions per 10 minutes per IP)
	$ip          = ross_get_client_ip();
	$rate_key    = 'ross_rate_' . md5( $ip );
	$submit_count = (int) get_transient( $rate_key );
	if ( $submit_count >= 5 ) {
		wp_send_json_error( array( 'message' => __( 'Too many booking requests. Please wait a few minutes before trying again.', 'appointment-booking-directory' ) ) );
	}

	$service_id   = isset( $_POST['service_id'] ) ? sanitize_text_field( wp_unslash( $_POST['service_id'] ) ) : '';
	$booking_date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$time_slot    = isset( $_POST['time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['time_slot'] ) ) : '';
	$client_name  = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
	$client_email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';

	if ( empty( $service_id ) || empty( $booking_date ) || empty( $time_slot ) || empty( $client_name ) || empty( $client_email ) ) {
		wp_send_json_error( array( 'message' => __( 'All fields are required.', 'appointment-booking-directory' ) ) );
	}

	// String length limits
	if ( mb_strlen( $client_name ) > 100 || mb_strlen( $client_email ) > 100 ) {
		wp_send_json_error( array( 'message' => __( 'Input length exceeded maximum allowed limit.', 'appointment-booking-directory' ) ) );
	}

	// Strict Service ID Validation
	$services = ross_get_services();
	if ( ! isset( $services[ $service_id ] ) ) {
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

	$auto_approve   = get_option( 'ross_booking_auto_approve', '0' );
	$initial_status = ( '1' === $auto_approve ) ? 'approved' : 'pending';

	$inserted = $wpdb->insert(
		$table_name,
		array(
			'service_id'   => $service_id,
			'booking_date' => $booking_date,
			'time_slot'    => $time_slot,
			'client_name'  => $client_name,
			'client_email' => $client_email,
			'status'       => $initial_status,
			'created_at'   => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		wp_send_json_error( array( 'message' => __( 'Failed to record booking. Please try again.', 'appointment-booking-directory' ) ) );
	}

	// Increment rate limiting transient
	set_transient( $rate_key, $submit_count + 1, 10 * MINUTE_IN_SECONDS );

	$booking_id   = $wpdb->insert_id;
	$service_name = $services[ $service_id ]['name'];

	// Dispatch email notification
	$notification_type = ( 'approved' === $initial_status ) ? 'approved' : 'new_booking';
	ross_send_booking_email( $notification_type, $booking_id, $client_email, $client_name, $service_name, $booking_date, $time_slot );

	wp_send_json_success( array(
		'booking_id'   => $booking_id,
		'service_name' => $service_name,
		'booking_date' => $booking_date,
		'time_slot'    => $time_slot,
		'client_name'  => $client_name,
		'status'       => $initial_status,
		'message'      => __( 'Booking submitted successfully!', 'appointment-booking-directory' ),
	) );
}
add_action( 'wp_ajax_ross_submit_booking', 'ross_submit_booking' );
add_action( 'wp_ajax_nopriv_ross_submit_booking', 'ross_submit_booking' );

/**
 * Register Admin Menu and Submenu Pages
 */
function ross_admin_menu() {
	add_menu_page(
		__( 'Appointment Booking Pro', 'appointment-booking-directory' ),
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

	add_submenu_page(
		'ross-appointments',
		__( 'Settings', 'appointment-booking-directory' ),
		__( 'Settings', 'appointment-booking-directory' ),
		'manage_options',
		'ross-appointments-settings',
		'ross_admin_settings_page'
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
			$existing_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $booking_id ) );

			if ( $existing_booking ) {
				$services     = ross_get_services();
				$service_name = isset( $services[ $existing_booking->service_id ] ) ? $services[ $existing_booking->service_id ]['name'] : $existing_booking->service_id;

				if ( 'approve' === $action ) {
					$updated = $wpdb->update(
						$table_name,
						array( 'status' => 'approved' ),
						array( 'id' => $booking_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( false !== $updated ) {
						ross_send_booking_email( 'approved', $booking_id, $existing_booking->client_email, $existing_booking->client_name, $service_name, $existing_booking->booking_date, $existing_booking->time_slot );
						$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Booking approved successfully and notification email sent.', 'appointment-booking-directory' ) . '</p></div>';
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
						ross_send_booking_email( 'cancelled', $booking_id, $existing_booking->client_email, $existing_booking->client_name, $service_name, $existing_booking->booking_date, $existing_booking->time_slot );
						$notice = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Booking cancelled successfully and notification email sent.', 'appointment-booking-directory' ) . '</p></div>';
					}
				}
			}
		}
	}

	// Filters & Search
	$status_filter  = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
	$service_filter = isset( $_GET['filter_service'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_service'] ) ) : '';
	$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	$where_clauses = array();
	$where_args    = array();

	if ( ! empty( $status_filter ) ) {
		$where_clauses[] = 'status = %s';
		$where_args[]    = $status_filter;
	}
	if ( ! empty( $service_filter ) ) {
		$where_clauses[] = 'service_id = %s';
		$where_args[]    = $service_filter;
	}
	if ( ! empty( $search_query ) ) {
		$like            = '%' . $wpdb->esc_like( $search_query ) . '%';
		$where_clauses[] = '(client_name LIKE %s OR client_email LIKE %s)';
		$where_args[]    = $like;
		$where_args[]    = $like;
	}

	$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
	$query     = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC";

	if ( ! empty( $where_args ) ) {
		$query = $wpdb->prepare( $query, $where_args );
	}

	$bookings = $wpdb->get_results( $query );
	$services = ross_get_services();

	// Analytics Stats
	$total_count    = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );
	$pending_count  = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'" ) );
	$approved_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'approved'" ) );
	$today_count    = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE booking_date = %s", current_time( 'Y-m-d' ) ) ) );

	$export_url = wp_nonce_url(
		add_query_arg(
			array(
				'page'   => 'ross-appointments',
				'action' => 'ross_export_csv',
			),
			admin_url( 'admin.php' )
		),
		'ross_export_csv_nonce'
	);

	?>
	<div class="wrap ross-admin-wrap">
		<div class="ross-admin-header">
			<h1><?php esc_html_e( 'Appointment Bookings Directory Pro', 'appointment-booking-directory' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary"><?php esc_html_e( 'Export to CSV', 'appointment-booking-directory' ); ?></a>
		</div>

		<?php echo wp_kses_post( $notice ); ?>

		<!-- Analytics Cards -->
		<div class="ross-analytics-grid">
			<div class="ross-card-stat total">
				<div class="ross-stat-title"><?php esc_html_e( 'Total Bookings', 'appointment-booking-directory' ); ?></div>
				<div class="ross-stat-val"><?php echo esc_html( $total_count ); ?></div>
			</div>
			<div class="ross-card-stat pending">
				<div class="ross-stat-title"><?php esc_html_e( 'Pending Approval', 'appointment-booking-directory' ); ?></div>
				<div class="ross-stat-val"><?php echo esc_html( $pending_count ); ?></div>
			</div>
			<div class="ross-card-stat approved">
				<div class="ross-stat-title"><?php esc_html_e( 'Approved Bookings', 'appointment-booking-directory' ); ?></div>
				<div class="ross-stat-val"><?php echo esc_html( $approved_count ); ?></div>
			</div>
			<div class="ross-card-stat today">
				<div class="ross-stat-title"><?php esc_html_e( 'Today\'s Bookings', 'appointment-booking-directory' ); ?></div>
				<div class="ross-stat-val"><?php echo esc_html( $today_count ); ?></div>
			</div>
		</div>

		<!-- Filter & Search Controls -->
		<form method="get" class="ross-filter-box">
			<input type="hidden" name="page" value="ross-appointments" />

			<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by name or email...', 'appointment-booking-directory' ); ?>" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; min-width: 220px;" />

			<select name="filter_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'appointment-booking-directory' ); ?></option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'appointment-booking-directory' ); ?></option>
				<option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php esc_html_e( 'Approved', 'appointment-booking-directory' ); ?></option>
				<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'appointment-booking-directory' ); ?></option>
			</select>

			<select name="filter_service">
				<option value=""><?php esc_html_e( 'All Services', 'appointment-booking-directory' ); ?></option>
				<?php foreach ( $services as $s ) : ?>
					<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $service_filter, $s['id'] ); ?>>
						<?php echo esc_html( $s['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter & Search', 'appointment-booking-directory' ); ?></button>
			<?php if ( ! empty( $status_filter ) || ! empty( $service_filter ) || ! empty( $search_query ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ross-appointments' ) ); ?>" class="button button-link"><?php esc_html_e( 'Reset Filters', 'appointment-booking-directory' ); ?></a>
			<?php endif; ?>
		</form>

		<div class="ross-table-container">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" style="width: 50px;"><?php esc_html_e( 'ID', 'appointment-booking-directory' ); ?></th>
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
										<span class="ross-badge ross-badge-approved"><?php esc_html_e( 'Approved', 'appointment-booking-directory' ); ?></span>
									<?php elseif ( 'cancelled' === $item->status ) : ?>
										<span class="ross-badge ross-badge-cancelled"><?php esc_html_e( 'Cancelled', 'appointment-booking-directory' ); ?></span>
									<?php else : ?>
										<span class="ross-badge ross-badge-pending"><?php esc_html_e( 'Pending', 'appointment-booking-directory' ); ?></span>
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
							<td colspan="8"><?php esc_html_e( 'No appointments match the current criteria.', 'appointment-booking-directory' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

/**
 * Admin Settings Page Callback
 */
function ross_admin_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'appointment-booking-directory' ) );
	}

	$notice = '';
	if ( isset( $_POST['ross_save_settings'] ) ) {
		check_admin_referer( 'ross_save_settings_nonce' );

		$business_name   = isset( $_POST['ross_booking_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ross_booking_business_name'] ) ) : '';
		$admin_email     = isset( $_POST['ross_booking_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['ross_booking_admin_email'] ) ) : '';
		$currency_symbol = isset( $_POST['ross_booking_currency_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['ross_booking_currency_symbol'] ) ) : '$';
		$slot_interval   = isset( $_POST['ross_booking_slot_interval'] ) ? sanitize_text_field( wp_unslash( $_POST['ross_booking_slot_interval'] ) ) : '60';
		$auto_approve    = isset( $_POST['ross_booking_auto_approve'] ) ? '1' : '0';

		update_option( 'ross_booking_business_name', $business_name );
		update_option( 'ross_booking_admin_email', $admin_email );
		update_option( 'ross_booking_currency_symbol', $currency_symbol );
		update_option( 'ross_booking_slot_interval', $slot_interval );
		update_option( 'ross_booking_auto_approve', $auto_approve );

		$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'appointment-booking-directory' ) . '</p></div>';
	}

	$business_name   = get_option( 'ross_booking_business_name', get_bloginfo( 'name' ) );
	$admin_email     = get_option( 'ross_booking_admin_email', get_option( 'admin_email' ) );
	$currency_symbol = get_option( 'ross_booking_currency_symbol', '$' );
	$slot_interval   = get_option( 'ross_booking_slot_interval', '60' );
	$auto_approve    = get_option( 'ross_booking_auto_approve', '0' );

	?>
	<div class="wrap ross-admin-wrap">
		<div class="ross-admin-header">
			<h1><?php esc_html_e( 'Appointment Booking Pro Settings', 'appointment-booking-directory' ); ?></h1>
		</div>

		<?php echo wp_kses_post( $notice ); ?>

		<div class="ross-admin-settings-card">
			<form method="post" action="">
				<?php wp_nonce_field( 'ross_save_settings_nonce' ); ?>

				<div class="ross-settings-group">
					<label for="ross_booking_business_name"><?php esc_html_e( 'Business / Organization Name', 'appointment-booking-directory' ); ?></label>
					<input type="text" id="ross_booking_business_name" name="ross_booking_business_name" value="<?php echo esc_attr( $business_name ); ?>" required />
					<p class="description"><?php esc_html_e( 'Used in outgoing booking email subject lines and client confirmations.', 'appointment-booking-directory' ); ?></p>
				</div>

				<div class="ross-settings-group">
					<label for="ross_booking_admin_email"><?php esc_html_e( 'Admin Notification Email', 'appointment-booking-directory' ); ?></label>
					<input type="email" id="ross_booking_admin_email" name="ross_booking_admin_email" value="<?php echo esc_attr( $admin_email ); ?>" required />
					<p class="description"><?php esc_html_e( 'Primary administrative email for notification records.', 'appointment-booking-directory' ); ?></p>
				</div>

				<div class="ross-settings-group">
					<label for="ross_booking_currency_symbol"><?php esc_html_e( 'Currency Symbol', 'appointment-booking-directory' ); ?></label>
					<input type="text" id="ross_booking_currency_symbol" name="ross_booking_currency_symbol" value="<?php echo esc_attr( $currency_symbol ); ?>" style="max-width: 80px;" required />
					<p class="description"><?php esc_html_e( 'Symbol displayed in directory service prices (e.g. $, €, £, ¥).', 'appointment-booking-directory' ); ?></p>
				</div>

				<div class="ross-settings-group">
					<label for="ross_booking_slot_interval"><?php esc_html_e( 'Time Slot Interval', 'appointment-booking-directory' ); ?></label>
					<select id="ross_booking_slot_interval" name="ross_booking_slot_interval">
						<option value="60" <?php selected( $slot_interval, '60' ); ?>><?php esc_html_e( '60 Minutes (Hourly)', 'appointment-booking-directory' ); ?></option>
						<option value="30" <?php selected( $slot_interval, '30' ); ?>><?php esc_html_e( '30 Minutes (Half-hourly)', 'appointment-booking-directory' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Controls the available time slots generated on the booking calendar.', 'appointment-booking-directory' ); ?></p>
				</div>

				<div class="ross-settings-group">
					<label>
						<input type="checkbox" name="ross_booking_auto_approve" value="1" <?php checked( $auto_approve, '1' ); ?> />
						<?php esc_html_e( 'Automatically Approve New Bookings', 'appointment-booking-directory' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'If enabled, new bookings are automatically confirmed without needing manual admin approval.', 'appointment-booking-directory' ); ?></p>
				</div>

				<p class="submit">
					<input type="submit" name="ross_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'appointment-booking-directory' ); ?>" />
				</p>
			</form>
		</div>
	</div>
	<?php
}

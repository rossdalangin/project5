<?php
/**
 * Test runner mocking WordPress core environment and functions.
 */

define( 'ABSPATH', __DIR__ . '/../' );

// Create mock wp-admin/includes directory and upgrade.php for testing
if ( ! file_exists( __DIR__ . '/../wp-admin/includes' ) ) {
	mkdir( __DIR__ . '/../wp-admin/includes', 0777, true );
}
if ( ! file_exists( __DIR__ . '/../wp-admin/includes/upgrade.php' ) ) {
	file_put_contents( __DIR__ . '/../wp-admin/includes/upgrade.php', "<?php\n// Mock upgrade.php\n" );
}

// Mock $wpdb
class MockWPDB {
	public $prefix = 'wp_';
	public $last_query;
	public $insert_id = 1;
	public $queries = array();
	public $rows = array();

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$replace = $arg;
			} else {
				$replace = "'" . addslashes( (string) $arg ) . "'";
			}
			$query = preg_replace( '/%[sd]/', $replace, $query, 1 );
		}
		return $query;
	}

	public function get_col( $query ) {
		$this->last_query = $query;
		$results = array();
		foreach ( $this->rows as $row ) {
			if ( strpos( $query, $row['service_id'] ) !== false && strpos( $query, $row['booking_date'] ) !== false && $row['status'] !== 'cancelled' ) {
				$results[] = $row['time_slot'];
			}
		}
		return $results;
	}

	public function get_var( $query ) {
		$this->last_query = $query;
		$count = 0;
		foreach ( $this->rows as $row ) {
			if ( strpos( $query, $row['service_id'] ) !== false && strpos( $query, $row['booking_date'] ) !== false && strpos( $query, $row['time_slot'] ) !== false && $row['status'] !== 'cancelled' ) {
				$count++;
			}
		}
		return $count;
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		$data['id'] = $this->insert_id;
		$this->rows[] = $data;
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		foreach ( $this->rows as &$row ) {
			if ( isset( $where['id'] ) && $row['id'] == $where['id'] ) {
				foreach ( $data as $k => $v ) {
					$row[ $k ] = $v;
				}
				return 1;
			}
		}
		return 0;
	}

	public function get_results( $query ) {
		$this->last_query = $query;
		$objs = array();
		foreach ( $this->rows as $row ) {
			$objs[] = (object) $row;
		}
		return $objs;
	}
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WP core functions
$actions = array();
$shortcodes = array();

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	global $actions;
	$actions[ $tag ][] = $callback;
}

function register_activation_hook( $file, $callback ) {
	global $actions;
	$actions['activate'][] = $callback;
}

function add_shortcode( $tag, $callback ) {
	global $shortcodes;
	$shortcodes[ $tag ] = $callback;
}

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_html_e( $text, $domain = 'default' ) { echo esc_html( $text ); }
function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
function __( $text, $domain = 'default' ) { return $text; }
function wp_kses_post( $text ) { return $text; }

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
}

function is_email( $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_die( $message = '' ) {
	die( $message );
}

function check_ajax_referer( $action, $query_arg = false, $die = true ) {
	if ( isset( $_REQUEST[ $query_arg ] ) && $_REQUEST[ $query_arg ] === 'valid_nonce' ) {
		return true;
	}
	if ( $die ) {
		throw new Exception( 'Invalid Nonce' );
	}
	return false;
}

function check_admin_referer( $action ) {
	return true;
}

function current_user_can( $capability ) {
	return true;
}

function plugin_dir_path( $file ) { return __DIR__ . '/../'; }
function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/appointment-booking-directory/'; }

function dbDelta( $sql ) {
	global $wpdb;
	$wpdb->queries[] = $sql;
}

function wp_send_json_success( $data = null ) {
	throw new Exception( 'JSON_SUCCESS: ' . json_encode( $data ) );
}

function wp_send_json_error( $data = null ) {
	throw new Exception( 'JSON_ERROR: ' . json_encode( $data ) );
}

function current_time( $type ) {
	if ( $type === 'Y-m-d' ) {
		return date( 'Y-m-d' );
	}
	return date( 'Y-m-d H:i:s' );
}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function admin_url( $path = '' ) { return 'http://example.com/wp-admin/' . $path; }
function wp_create_nonce( $action ) { return 'valid_nonce'; }
function add_menu_page() {}
function add_submenu_page() {}
function wp_nonce_url( $actionurl, $action ) { return $actionurl . '&_wpnonce=valid_nonce'; }
function add_query_arg( $key, $value = false, $url = false ) {
	if ( is_array( $key ) ) {
		$query = http_build_query( $key );
		return $url ? $url . '?' . $query : '?' . $query;
	}
	return $url ? $url . '?' . $key . '=' . $value : '?' . $key . '=' . $value;
}

// Load plugin main file
require_once __DIR__ . '/../appointment-booking-directory.php';

// Run tests
echo "--- Running Tests ---\n";

// Test 1: DB Table Creation Hook
echo "Test 1: Activation Hook & DB Creation... ";
ross_booking_activate();
assert( ! empty( $wpdb->queries ), 'dbDelta query executed' );
assert( strpos( $wpdb->queries[0], 'wp_appointment_slots' ) !== false, 'Table name present in query' );
echo "PASSED\n";

// Test 2: Get Available Slots via AJAX
echo "Test 2: ross_get_available_slots... ";
$_REQUEST['security'] = 'valid_nonce';
$_REQUEST['service_id'] = 'service-1';
$_REQUEST['booking_date'] = date( 'Y-m-d', strtotime( '+1 day' ) );

try {
	ross_get_available_slots();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'JSON_SUCCESS' ) !== false, 'Returned JSON success' );
	$json = json_decode( str_replace( 'JSON_SUCCESS: ', '', $e->getMessage() ), true );
	assert( count( $json['slots'] ) === 7, 'All 7 slots returned' );
	assert( $json['slots'][0]['available'] === true, 'Slot 0 is available' );
}
echo "PASSED\n";

// Test 3: Submit Booking via AJAX
echo "Test 3: ross_submit_booking... ";
$_POST['security'] = 'valid_nonce';
$_POST['service_id'] = 'service-1';
$_POST['booking_date'] = date( 'Y-m-d', strtotime( '+1 day' ) );
$_POST['time_slot'] = '09:00 AM';
$_POST['client_name'] = 'John Doe';
$_POST['client_email'] = 'john@example.com';

try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'JSON_SUCCESS' ) !== false, 'Booking submitted successfully' );
}
echo "PASSED\n";

// Test 4: Re-fetching slots shows 09:00 AM as unavailable
echo "Test 4: Verify booked slot unavailable... ";
try {
	ross_get_available_slots();
} catch ( Exception $e ) {
	$json = json_decode( str_replace( 'JSON_SUCCESS: ', '', $e->getMessage() ), true );
	assert( $json['slots'][0]['time_slot'] === '09:00 AM', 'First slot is 09:00 AM' );
	assert( $json['slots'][0]['available'] === false, '09:00 AM is now unavailable' );
}
echo "PASSED\n";

// Test 5: Double booking same slot returns error
echo "Test 5: Prevent double booking... ";
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'JSON_ERROR' ) !== false, 'Returned JSON error for double booking' );
}
echo "PASSED\n";

// Test 6: Strict validation testing (invalid service, invalid slot, past date)
echo "Test 6: Strict validation checks... ";
$_POST['service_id'] = 'invalid-service';
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Invalid service selected.' ) !== false, 'Rejects invalid service' );
}

$_POST['service_id'] = 'service-1';
$_POST['booking_date'] = '2000-01-01'; // Past date
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Booking date cannot be in the past.' ) !== false, 'Rejects past date' );
}

$_POST['booking_date'] = date( 'Y-m-d', strtotime( '+1 day' ) );
$_POST['time_slot'] = '25:00 PM'; // Invalid time slot
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Invalid time slot selected.' ) !== false, 'Rejects invalid time slot' );
}
echo "PASSED\n";

// Test 7: Admin Dashboard Page render
echo "Test 7: Admin Dashboard Page render... ";
ob_start();
ross_admin_appointments_page();
$output = ob_get_clean();
assert( strpos( $output, 'Appointment Bookings Directory' ) !== false, 'Admin page title rendered' );
assert( strpos( $output, 'John Doe' ) !== false, 'Booked client name rendered in admin table' );
echo "PASSED\n";

// Test 8: Admin Approve Action
echo "Test 8: Admin Approve Action... ";
$_GET['action'] = 'approve';
$_GET['booking_id'] = 2;
ob_start();
ross_admin_appointments_page();
$admin_out = ob_get_clean();
assert( strpos( $admin_out, 'Booking approved successfully.' ) !== false, 'Approval message displayed' );
assert( strpos( $admin_out, 'Approved' ) !== false, 'Status updated to Approved' );
echo "PASSED\n";

// Cleanup mock wp-admin directory
unlink( __DIR__ . '/../wp-admin/includes/upgrade.php' );
rmdir( __DIR__ . '/../wp-admin/includes' );
rmdir( __DIR__ . '/../wp-admin' );

echo "ALL TESTS PASSED SUCCESSFULLY!\n";

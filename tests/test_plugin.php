<?php
/**
 * Test runner mocking WordPress core environment and functions.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'MINUTE_IN_SECONDS', 60 );

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

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
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
		if ( strpos( $query, 'COUNT(*)' ) !== false ) {
			if ( strpos( $query, "status = 'pending'" ) !== false ) {
				$cnt = 0;
				foreach ( $this->rows as $r ) { if ( $r['status'] === 'pending' ) $cnt++; }
				return $cnt;
			}
			if ( strpos( $query, "status = 'approved'" ) !== false ) {
				$cnt = 0;
				foreach ( $this->rows as $r ) { if ( $r['status'] === 'approved' ) $cnt++; }
				return $cnt;
			}
			if ( strpos( $query, 'booking_date =' ) !== false ) {
				$cnt = 0;
				foreach ( $this->rows as $r ) { if ( $r['booking_date'] === date('Y-m-d') ) $cnt++; }
				return $cnt;
			}
			return count( $this->rows );
		}
		return 0;
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

	public function get_row( $query ) {
		$this->last_query = $query;
		foreach ( $this->rows as $row ) {
			if ( strpos( $query, (string) $row['id'] ) !== false ) {
				return (object) $row;
			}
		}
		return null;
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

// Mock WP core functions & Options/Transients store
$actions = array();
$shortcodes = array();
$sent_emails = array();
$options_store = array();
$transients_store = array();

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

function get_option( $opt, $default = false ) {
	global $options_store;
	return array_key_exists( $opt, $options_store ) ? $options_store[ $opt ] : $default;
}

function update_option( $opt, $val ) {
	global $options_store;
	$options_store[ $opt ] = $val;
	return true;
}

function get_transient( $key ) {
	global $transients_store;
	return isset( $transients_store[ $key ] ) ? $transients_store[ $key ] : false;
}

function set_transient( $key, $val, $expiration = 0 ) {
	global $transients_store;
	$transients_store[ $key ] = $val;
	return true;
}

function get_bloginfo( $show = '' ) { return 'Test Site'; }
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	global $sent_emails;
	$sent_emails[] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	);
	return true;
}

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_html_e( $text, $domain = 'default' ) { echo esc_html( $text ); }
function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
function esc_attr_e( $text, $domain = 'default' ) { echo esc_attr( $text ); }
function __( $text, $domain = 'default' ) { return $text; }
function wp_kses_post( $text ) { return $text; }
function selected( $selected, $current = true, $echo = true ) {
	$result = ( (string) $selected === (string) $current ) ? "selected='selected'" : '';
	if ( $echo ) echo $result;
	return $result;
}
function checked( $checked, $current = true, $echo = true ) {
	$result = ( (string) $checked === (string) $current ) ? "checked='checked'" : '';
	if ( $echo ) echo $result;
	return $result;
}
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="valid_nonce" />'; }

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

// Test 1: DB Table Creation Hook & Default Options
echo "Test 1: Activation Hook, DB Creation & Options init... ";
ross_booking_activate();
assert( ! empty( $wpdb->queries ), 'dbDelta query executed' );
assert( strpos( $wpdb->queries[0], 'wp_appointment_slots' ) !== false, 'Table name present in query' );
assert( get_option( 'ross_booking_currency_symbol' ) === '$', 'Default currency initialized' );
echo "PASSED\n";

// Test 2: Honeypot Protection
echo "Test 2: Honeypot Spam Protection... ";
$_POST['security'] = 'valid_nonce';
$_POST['service_id'] = 'service-1';
$_POST['booking_date'] = date( 'Y-m-d', strtotime( '+1 day' ) );
$_POST['time_slot'] = '09:00 AM';
$_POST['client_name'] = 'Spam Bot';
$_POST['client_email'] = 'bot@spam.test';
$_POST['ross_hp'] = 'I am a bot';

try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Spam detection triggered.' ) !== false, 'Blocked honeypot spam bot' );
}
unset( $_POST['ross_hp'] );
echo "PASSED\n";

// Test 3: Normal Booking Submission
echo "Test 3: Normal Booking Submission... ";
$_POST['client_name'] = 'John Doe';
$_POST['client_email'] = 'john@example.com';
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'JSON_SUCCESS' ) !== false, 'Booking submitted successfully' );
	assert( count( $sent_emails ) === 1, 'Confirmation email dispatched' );
}
echo "PASSED\n";

// Test 4: Rate Limiting
echo "Test 4: IP Rate Limiting... ";
$ip = ross_get_client_ip();
set_transient( 'ross_rate_' . md5( $ip ), 5, 600 ); // Max limit reached
$_POST['time_slot'] = '10:00 AM';
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Too many booking requests' ) !== false, 'Rate limit blocked flood' );
}
set_transient( 'ross_rate_' . md5( $ip ), 1, 600 ); // Reset
echo "PASSED\n";

// Test 5: Email Header Injection Protection
echo "Test 5: Header Injection Protection in Emails... ";
$header_inject_email = "victim@test.com\r\nBcc: evil@test.com";
$header_inject_name = "Attacker\r\nSubject: Injected";
ross_send_booking_email( 'new_booking', 99, $header_inject_email, $header_inject_name, 'General Consultation', '2026-11-20', '09:00 AM' );
$last_email = end( $sent_emails );
assert( strpos( $last_email['to'], "\r" ) === false, 'CR stripped from to field' );
assert( strpos( $last_email['to'], "\n" ) === false, 'LF stripped from to field' );
assert( strpos( $last_email['to'], 'evil@test.com' ) === false, 'Bcc header neutralized' );
echo "PASSED\n";

// Test 6: String Length Boundaries
echo "Test 6: Input Length Validation... ";
$_POST['client_name'] = str_repeat( 'A', 150 );
try {
	ross_submit_booking();
} catch ( Exception $e ) {
	assert( strpos( $e->getMessage(), 'Input length exceeded' ) !== false, 'Rejected overlength name' );
}
$_POST['client_name'] = 'John Doe';
echo "PASSED\n";

// Test 7: Admin Search & Render
echo "Test 7: Admin Dashboard Search & Render... ";
$_GET['s'] = 'John';
ob_start();
ross_admin_appointments_page();
$output = ob_get_clean();
assert( strpos( $output, 'Appointment Bookings Directory Pro' ) !== false, 'Admin page rendered' );
assert( strpos( $output, 'John Doe' ) !== false, 'Searched booking client found' );
echo "PASSED\n";

// Cleanup mock wp-admin directory
unlink( __DIR__ . '/../wp-admin/includes/upgrade.php' );
rmdir( __DIR__ . '/../wp-admin/includes' );
rmdir( __DIR__ . '/../wp-admin' );

echo "ALL TESTS PASSED SUCCESSFULLY!\n";

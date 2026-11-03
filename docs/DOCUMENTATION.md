# Technical & User Documentation

## 1. System Requirements & Compatibility

- **WordPress:** Version 5.6 or higher.
- **PHP:** 7.4 or higher (fully compatible with PHP 8.0, 8.1, 8.2, and 8.3).
- **Database:** MySQL 5.7+ or MariaDB 10.3+.
- **Web Server:** Apache (with `mod_rewrite`), Nginx, or LiteSpeed.
- **External Dependencies:** **NONE**. All features, styling, and JavaScript logic run entirely local to the host.

---

## 2. Architectural Overview

The plugin follows standard WordPress plugin architecture:
- `appointment-booking-directory.php`: Central orchestrator containing hooks, table activation logic, REST/AJAX endpoints, options handling, and admin page callbacks.
- `assets/css/frontend.css`: Scoped frontend styling for appointment booking widgets, step indicators, time slot pills, and confirmation cards.
- `assets/css/admin.css`: Native WP Admin styling enhancing dashboard metrics, filter toolbars, status chips, and settings forms.
- `assets/js/frontend.js`: Pure vanilla JavaScript (ES6) utilizing `fetch()` to handle AJAX requests without external JS library dependencies.

---

## 3. Database Architecture & Schema

Upon activation, the plugin calls `register_activation_hook()` executing `dbDelta()` to create or update the dedicated table `{$wpdb->prefix}appointment_slots`.

### Table Schema

| Column | Type | Default | Attributes | Description |
|---|---|---|---|---|
| `id` | `bigint(20)` | *None* | `NOT NULL AUTO_INCREMENT PRIMARY KEY` | Unique appointment identifier |
| `service_id` | `varchar(50)` | *None* | `NOT NULL` | ID of the selected directory service |
| `booking_date` | `date` | *None* | `NOT NULL` | Scheduled date (`YYYY-MM-DD`) |
| `time_slot` | `varchar(50)` | *None* | `NOT NULL` | Scheduled time (e.g., `09:00 AM`) |
| `client_name` | `varchar(100)` | *None* | `NOT NULL` | Full name of the client |
| `client_email` | `varchar(100)` | *None* | `NOT NULL` | Validated email address |
| `status` | `varchar(20)` | `'pending'` | `NOT NULL` | Status: `pending`, `approved`, or `cancelled` |
| `created_at` | `datetime` | `CURRENT_TIMESTAMP` | `NOT NULL` | Timestamp when the booking was submitted |

---

## 4. Frontend AJAX API Reference

All AJAX endpoints are registered under both logged-in (`wp_ajax_`) and guest (`wp_ajax_nopriv_`) actions.

### 4.1. Get Available Slots (`ross_get_available_slots`)
- **HTTP Method:** `GET`
- **Security Check:** `check_ajax_referer('ross_booking_nonce', 'security')`
- **Request Parameters:**
  - `service_id`: (string) Validated against allowed service IDs.
  - `booking_date`: (string) Validated `YYYY-MM-DD`, cannot be in the past.
- **Response Format (JSON):**
  ```json
  {
    "success": true,
    "data": {
      "service_id": "service-1",
      "booking_date": "2026-11-15",
      "slots": [
        { "time_slot": "09:00 AM", "available": true },
        { "time_slot": "10:00 AM", "available": false }
      ]
    }
  }
  ```

### 4.2. Submit Booking (`ross_submit_booking`)
- **HTTP Method:** `POST`
- **Security Check:** `check_ajax_referer('ross_booking_nonce', 'security')`
- **Protections:**
  - Silent honeypot detection (`ross_hp`).
  - IP-based transient rate limiting (max 5 requests per 10 minutes per IP).
  - Maximum string length check (100 characters max for names and emails).
  - Concurrency collision check: prevents double booking if two users submit the same slot simultaneously.
- **Request Parameters:**
  - `service_id`: (string)
  - `booking_date`: (string `YYYY-MM-DD`)
  - `time_slot`: (string)
  - `client_name`: (string)
  - `client_email`: (string)
  - `ross_hp`: (string, must be empty)
- **Response Format (JSON):**
  ```json
  {
    "success": true,
    "data": {
      "booking_id": 42,
      "service_name": "General Consultation",
      "booking_date": "2026-11-15",
      "time_slot": "09:00 AM",
      "client_name": "Jane Doe",
      "status": "pending",
      "message": "Booking submitted successfully!"
    }
  }
  ```

---

## 5. Shortcode Reference

To embed the booking interface on any page, post, or widget area:
```text
[appointment_booking]
```
The shortcode renders a 3-step responsive form:
1. **Step 1:** Select service from directory and select appointment date (with "Today" / "Tomorrow" quick buttons).
2. **Step 2:** Choose an available time slot pill with live accessibility indicators (`aria-pressed`).
3. **Step 3:** Enter client details and confirm booking with instant confirmation card display.

---

## 6. WP Admin Settings Configuration

Access via **Appointments -> Settings**:
1. **Business Name:** Used across customer notification emails and receipts.
2. **Admin Notification Email:** Administrative address receiving appointment alerts.
3. **Currency Symbol:** Configurable currency symbol ($, €, £, ¥, etc.) shown in directory prices.
4. **Time Slot Interval:** Choose between 60 minutes (hourly) or 30 minutes (half-hourly).
5. **Auto-Approval:** Toggle whether bookings are automatically approved upon submission.

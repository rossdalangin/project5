# Appointment Booking & Service Directory Pro

> A high-performance, self-contained WordPress appointment booking and service directory plugin with zero external SaaS dependencies.

## Key Features

- **Self-Contained Architecture:** Operates entirely within your WordPress environment. No monthly subscriptions, no external APIs, and full data privacy and GDPR compliance.
- **Custom Database Storage:** Employs a dedicated database table (`{$wpdb->prefix}appointment_slots`) created via `dbDelta()` for maximum speed and scalability.
- **AJAX Real-Time Booking:** Vanilla JavaScript `fetch()` API powers dynamic date selection and slot availability without page refreshes.
- **Enterprise-Grade Security:**
  - Nonce validation (`check_ajax_referer` / `check_admin_referer`).
  - Strict input sanitization and email validation.
  - Parameterized queries via `$wpdb->prepare()`.
  - Silent anti-spam honeypot detection.
  - Transient-based IP rate limiting.
  - Email header injection prevention.
- **Native WP Admin Dashboard:**
  - Real-time KPI analytics (Total, Pending, Approved, Today).
  - Status and service filtering, plus client search.
  - One-click approval and cancellation workflows.
  - One-click CSV export of all bookings.
  - Dedicated configuration settings page.
- **Automated Notifications:** Native `wp_mail()` notifications sent on new booking requests, approvals, and cancellations.

## Quick Start

1. Place the plugin directory into `wp-content/plugins/`.
2. Activate via WordPress Admin -> **Plugins**.
3. Insert `[appointment_booking]` into any page or post.
4. Manage appointments under **Appointments -> Manage Bookings**.

For complete documentation, see the `docs/` folder:
- [Technical Documentation](docs/DOCUMENTATION.md)
- [Standard Operating Procedures (SOP)](docs/SOP.md)
- [Administrator Instructions](docs/INSTRUCTIONS.md)
- [Sales & Marketing Materials](docs/MARKETING.md)

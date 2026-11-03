# Administrator & Installation Instructions

## 1. Installation Methods

### Method A: Direct Upload via WordPress Dashboard
1. Compress the plugin directory into a `.zip` archive (e.g., `appointment-booking-directory.zip`).
2. In your WordPress admin dashboard, go to **Plugins -> Add New**.
3. Click the **Upload Plugin** button at the top of the page.
4. Choose the zip file and click **Install Now**.
5. Once installed, click **Activate Plugin**.

### Method B: Manual FTP / SFTP Installation
1. Connect to your web hosting server using an FTP client (FileZilla, Cyberduck, etc.).
2. Navigate to your WordPress root directory, then `wp-content/plugins/`.
3. Upload the uncompressed `appointment-booking-directory` folder into the `plugins/` directory.
4. Log in to WordPress Admin, navigate to **Plugins -> Installed Plugins**, find **Appointment Booking & Service Directory Pro**, and click **Activate**.

---

## 2. Post-Activation Verification Checklist

After activation, confirm the following items:
- [ ] Custom table created: Verify that `{$wpdb->prefix}appointment_slots` exists in the database.
- [ ] Admin menu visible: Confirm **Appointments** appears in the left WordPress admin sidebar with sub-menus **Manage Bookings** and **Settings**.
- [ ] Default settings populated: Visit **Appointments -> Settings** and confirm business name, admin email, and currency symbol are populated with your site defaults.

---

## 3. Configuration Walkthrough

1. Go to **Appointments -> Settings**.
2. **Business / Organization Name:** Enter the public name of your business or practice (e.g., "Apex Dental Clinic"). This name is used in all client notifications.
3. **Admin Notification Email:** Provide the operational email where booking notifications should be received.
4. **Currency Symbol:** Specify your local currency symbol ($, €, £, etc.).
5. **Time Slot Interval:** Choose either `60 Minutes` or `30 Minutes` depending on your typical service session length.
6. **Auto-Approval:** Check this box if you want bookings automatically confirmed upon submission. Leave unchecked if bookings require manual confirmation by staff.
7. Click **Save Settings**.

---

## 4. Frontend Shortcode Implementation

You can place the appointment booking interface on any page or post.

### Using the WordPress Block Editor (Gutenberg)
1. Edit or create the page where you want the booking widget (e.g., `/book-appointment/`).
2. Click the `+` button to add a new block.
3. Search for and insert the **Shortcode** block.
4. Enter `[appointment_booking]`.
5. Publish or Update the page.

### Using Page Builders (Elementor, Beaver Builder, Divi)
1. Open the page in your builder.
2. Drag and drop a Shortcode or HTML module into the desired column.
3. Enter `[appointment_booking]`.
4. Save and preview the page.

---

## 5. Troubleshooting & FAQ

### Q: Why do slots not load when selecting a date?
- **A:** Ensure the date selected is today or a future date. Past dates are blocked by security validation. Check your browser console to verify `admin-ajax.php` is reachable and returns HTTP 200.

### Q: Why are email notifications not being received?
- **A:** The plugin uses WordPress's native `wp_mail()` function. If your web host disables PHP `mail()`, install an SMTP plugin (such as WP Mail SMTP) to route emails through your company's transactional mail provider.

### Q: Does this plugin slow down page speed?
- **A:** No. The plugin uses vanilla JavaScript with zero dependencies (no jQuery, React, or heavy external bundles). Assets are only ~10KB and load asynchronously.

# Comprehensive Tutorials & Developer Guides

## Tutorial 1: Quick 5-Minute Setup for Site Owners

### Objective
Get a working appointment scheduling calendar on your site in less than 5 minutes.

### Steps
1. **Activate the Plugin:**
   - In WordPress admin, go to **Plugins -> Installed Plugins** and click **Activate** next to **Appointment Booking & Service Directory Pro**.
2. **Configure Business Details:**
   - Go to **Appointments -> Settings**.
   - Fill in your **Business Name** and **Admin Notification Email**.
   - Choose your preferred currency symbol (e.g., `$`) and slot interval (`60 Minutes` or `30 Minutes`).
   - Click **Save Settings**.
3. **Publish the Booking Page:**
   - Go to **Pages -> Add New**.
   - Title the page "Book an Appointment".
   - Insert a Shortcode block with `[appointment_booking]`.
   - Click **Publish**.
4. **Test the Workflow:**
   - Open your new booking page in an incognito window.
   - Select a service, pick today or tomorrow's date, choose an available time slot, and submit the booking.
   - Return to **Appointments -> Manage Bookings** in your dashboard to view and approve the request.

---

## Tutorial 2: Page Builder Integrations (Elementor & Gutenberg)

### Gutenberg Block Editor
1. Open any page or post in the Block Editor.
2. Type `/shortcode` and press Enter.
3. Paste `[appointment_booking]`.
4. Optionally, wrap the shortcode block in a **Columns** or **Group** block to constrain width or add background colors.

### Elementor Page Builder
1. Open the target page with Elementor.
2. In the widget search bar, type `Shortcode`.
3. Drag the **Shortcode** widget into a new section.
4. In the left panel, paste `[appointment_booking]`.
5. Under the **Advanced** tab, configure padding (e.g., 20px) and box shadows if desired.
6. Click **Update**.

---

## Tutorial 3: Customizing Services & Pricing (Developer Guide)

You can programmatically customize or add new services to the directory without modifying core plugin code using the `ross_booking_services` filter.

### Code Snippet (Add to child theme `functions.php` or custom plugin):
```php
add_filter( 'ross_booking_services', function( $services ) {
    // Add a new custom VIP service
    $services['service-vip'] = array(
        'id'          => 'service-vip',
        'name'        => 'Executive Mastermind Session',
        'category'    => 'Executive',
        'price'       => '$500',
        'duration'    => '120 min',
        'description' => 'A 2-hour private strategy session with senior partners.',
    );
    return $services;
} );
```

---

## Tutorial 4: Custom Time Slots Filter

If your business operates on custom hours (e.g. evening hours or weekend slots), use the `ross_booking_time_slots` filter.

### Code Snippet:
```php
add_filter( 'ross_booking_time_slots', function( $slots, $interval ) {
    // Custom evening schedule
    return array(
        '05:00 PM',
        '06:00 PM',
        '07:00 PM',
        '08:00 PM',
    );
}, 10, 2 );
```

---

## Tutorial 5: Webhook & CRM Sync on New Bookings

Automatically send customer booking details to Zapier, Make, Slack, or your CRM using the `ross_booking_created` action hook.

### Code Snippet:
```php
add_action( 'ross_booking_created', function( $booking_id, $booking_data ) {
    $webhook_url = 'https://hooks.zapier.com/hooks/catch/xxxxxx/yyyyyy/';

    wp_remote_post( $webhook_url, array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'booking_id'   => $booking_id,
            'client_name'  => $booking_data['client_name'],
            'client_email' => $booking_data['client_email'],
            'booking_date' => $booking_data['booking_date'],
            'time_slot'    => $booking_data['time_slot'],
            'service_id'   => $booking_data['service_id'],
            'status'       => $booking_data['status'],
        ) ),
    ) );
}, 10, 2 );
```

---

## Tutorial 6: Custom CSS Branding Recipes

To match your company’s brand colors, add the following CSS to **Appearance -> Customize -> Additional CSS**:

```css
/* Change primary theme color to emerald green */
.ross-slot-btn {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
    color: #065f46 !important;
}

.ross-slot-btn:hover:not(.disabled) {
    background: #059669 !important;
    color: #ffffff !important;
}

.ross-slot-btn.selected {
    background: #047857 !important;
    border-color: #047857 !important;
    color: #ffffff !important;
}

.ross-btn-submit {
    background: #059669 !important;
}

.ross-btn-submit:hover {
    background: #047857 !important;
}

.ross-step.active .ross-step-num {
    background: #059669 !important;
}

.ross-step.active {
    color: #059669 !important;
}
```

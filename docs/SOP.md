# Standard Operating Procedures (SOP)

## SOP-001: Daily Appointment Review & Triage

### Purpose
Ensure that customer booking requests submitted through the website are reviewed and confirmed promptly within standard service-level agreements (SLAs).

### Scope
Applies to front-desk staff, practice administrators, consultants, and site managers.

### Procedure
1. **Access Dashboard:** Log in to WordPress Admin and navigate to **Appointments -> Manage Bookings**.
2. **Review Metrics:** Check the **Pending Approval** card at the top. If the number is greater than 0, triage pending requests immediately.
3. **Filter Pending Requests:** Select "Pending" from the status filter dropdown and click **Filter & Search**.
4. **Evaluate Details:** Check the client name, email address, requested service, and requested time slot.
5. **Take Action:**
   - **Approve:** Click the **Approve** button. This automatically updates the status in the database to `approved` and dispatches an automated approval confirmation email to the client.
   - **Cancel/Decline:** If the practitioner is unavailable or the request is invalid, click **Cancel**. Confirm the browser prompt. The slot is freed up in real time for other customers, and a status update email is automatically sent to the client.

---

## SOP-002: Searching and Modifying Existing Appointments

### Purpose
Rapidly locate client bookings when customers call or email for modifications or cancellations.

### Procedure
1. Navigate to **Appointments -> Manage Bookings**.
2. In the search box, enter the client's name or email address and click **Filter & Search**.
3. Locate the appointment record in the results table.
4. If a cancellation is requested, click **Cancel**. Confirm the prompt.
5. If the client wishes to reschedule to a new date or time, instruct the client to select a new available slot via the website, or submit a new slot on their behalf using the frontend booking form.

---

## SOP-003: Weekly CSV Export, Backup & Import

### Purpose
Export operational booking data for external accounting, scheduling integration, or bulk importing historical client appointments.

### Export Procedure
1. Log in to WordPress Admin.
2. Navigate to **Appointments -> Manage Bookings** or **Appointments -> Settings & Data**.
3. Click the blue **Export to CSV** button.
4. The system will download a file named `appointment-bookings-YYYY-MM-DD.csv`.
5. Open the CSV file in Excel, Google Sheets, or import into your CRM/accounting software.

### Import Procedure
1. Navigate to **Appointments -> Settings & Data**.
2. Under **Data Management**, click **Download Sample CSV Template** to review the expected column schema (`service_id`, `booking_date`, `time_slot`, `client_name`, `client_email`, `status`).
3. Prepare your spreadsheet matching the required headers. Dates must follow `YYYY-MM-DD`.
4. Choose the file in the upload field and click **Upload and Import CSV**.
5. Check the system notice for the number of imported vs. skipped rows. Conflicting/duplicate active time slots are automatically skipped to prevent double booking.

---

## SOP-004: Managing Slot Intervals & Auto-Approval

### Purpose
Adjust appointment duration, slot frequency, or currency as business requirements evolve.

### Procedure
1. Navigate to **Appointments -> Settings & Data**.
2. To offer more frequent appointment times (e.g., during busy holiday seasons), adjust **Time Slot Interval** from `60 Minutes (Hourly)` to `30 Minutes (Half-hourly)`.
3. If staff are managing real-time schedules outside of WordPress and cannot review each submission manually, enable **Automatically Approve New Bookings**.
4. Click **Save Settings**.
5. Test the frontend booking page to verify updated slot listings.

---

## SOP-005: Security Incident & Anti-Spam Management

### Purpose
Address potential bot floods or unauthorized submission attempts.

### Procedure
1. The plugin includes automatic silent honeypot fields (`ross_hp`) and IP rate limiting (maximum 5 submissions per 10 minutes per IP).
2. If unexpected spam records appear in **Appointments -> Manage Bookings**:
   - Immediately mark the offending records as **Cancelled** to free slots.
   - Review server access logs for repeated IP addresses submitting to `admin-ajax.php?action=ross_submit_booking`.
   - Block malicious IPs at the server level (e.g. Cloudflare or `.htaccess`).

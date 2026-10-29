document.addEventListener('DOMContentLoaded', function () {
    const serviceSelect = document.getElementById('ross-service-select');
    const dateInput = document.getElementById('ross-booking-date');
    const slotsContainer = document.getElementById('ross-slots-container');
    const slotsList = document.getElementById('ross-slots-list');
    const bookingForm = document.getElementById('ross-booking-form');
    const selectedSlotInput = document.getElementById('ross-selected-slot');
    const messageContainer = document.getElementById('ross-booking-message');

    if (!serviceSelect || !dateInput) {
        return;
    }

    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);

    function showMessage(msg, isSuccess = true) {
        messageContainer.style.display = 'block';
        messageContainer.className = isSuccess ? 'ross-message ross-success' : 'ross-message ross-error';
        messageContainer.textContent = msg;
    }

    function renderConfirmationCard(data) {
        messageContainer.style.display = 'block';
        messageContainer.className = 'ross-message ross-success ross-confirmation-card';
        messageContainer.textContent = '';

        const title = document.createElement('h3');
        title.style.margin = '0 0 10px 0';
        title.textContent = '🎉 Appointment Confirmed!';

        const desc = document.createElement('p');
        desc.style.margin = '0 0 15px 0';
        desc.textContent = 'Your booking request has been submitted successfully and is pending approval.';

        const detailsList = document.createElement('ul');
        detailsList.style.listStyle = 'none';
        detailsList.style.padding = '0';
        detailsList.style.margin = '0 0 15px 0';

        const fields = [
            { label: 'Booking Reference', val: '#' + data.booking_id },
            { label: 'Client Name', val: data.client_name },
            { label: 'Service', val: data.service_name },
            { label: 'Date', val: data.booking_date },
            { label: 'Time Slot', val: data.time_slot }
        ];

        fields.forEach(f => {
            const li = document.createElement('li');
            li.style.padding = '4px 0';
            const bold = document.createElement('strong');
            bold.textContent = f.label + ': ';
            li.appendChild(bold);
            li.appendChild(document.createTextNode(f.val));
            detailsList.appendChild(li);
        });

        messageContainer.appendChild(title);
        messageContainer.appendChild(desc);
        messageContainer.appendChild(detailsList);
    }

    function clearMessage() {
        messageContainer.style.display = 'none';
        messageContainer.textContent = '';
    }

    function fetchAvailableSlots() {
        const serviceId = serviceSelect.value;
        const bookingDate = dateInput.value;

        if (!serviceId || !bookingDate) {
            slotsContainer.style.display = 'none';
            bookingForm.style.display = 'none';
            return;
        }

        clearMessage();
        slotsList.textContent = '';
        const loadingPara = document.createElement('p');
        loadingPara.className = 'ross-loading';
        loadingPara.textContent = 'Loading available slots...';
        slotsList.appendChild(loadingPara);

        slotsContainer.style.display = 'block';
        bookingForm.style.display = 'none';
        selectedSlotInput.value = '';

        const params = new URLSearchParams({
            action: 'ross_get_available_slots',
            security: rossBookingData.nonce,
            service_id: serviceId,
            booking_date: bookingDate
        });

        fetch(rossBookingData.ajax_url + '?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success && res.data && res.data.slots) {
                renderSlots(res.data.slots);
            } else {
                slotsList.textContent = '';
                const errPara = document.createElement('p');
                errPara.className = 'ross-error';
                errPara.textContent = (res.data && res.data.message) ? res.data.message : 'Error fetching slots.';
                slotsList.appendChild(errPara);
            }
        })
        .catch(err => {
            slotsList.textContent = '';
            const errPara = document.createElement('p');
            errPara.className = 'ross-error';
            errPara.textContent = 'Network error occurred. Please try again.';
            slotsList.appendChild(errPara);
        });
    }

    function renderSlots(slots) {
        slotsList.textContent = '';
        if (slots.length === 0) {
            const noSlotsPara = document.createElement('p');
            noSlotsPara.textContent = 'No available slots for this date.';
            slotsList.appendChild(noSlotsPara);
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'ross-slots-grid';

        slots.forEach(slotObj => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ross-slot-btn' + (slotObj.available ? '' : ' disabled');
            btn.textContent = slotObj.time_slot;
            btn.disabled = !slotObj.available;

            if (slotObj.available) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.ross-slot-btn').forEach(b => b.classList.remove('selected'));
                    btn.classList.add('selected');
                    selectedSlotInput.value = slotObj.time_slot;
                    bookingForm.style.display = 'block';
                    bookingForm.scrollIntoView({ behavior: 'smooth' });
                });
            }

            grid.appendChild(btn);
        });

        slotsList.appendChild(grid);
    }

    serviceSelect.addEventListener('change', fetchAvailableSlots);
    dateInput.addEventListener('change', fetchAvailableSlots);

    if (bookingForm) {
        bookingForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearMessage();

            const serviceId = serviceSelect.value;
            const bookingDate = dateInput.value;
            const timeSlot = selectedSlotInput.value;
            const clientName = document.getElementById('ross-client-name').value.trim();
            const clientEmail = document.getElementById('ross-client-email').value.trim();

            if (!serviceId || !bookingDate || !timeSlot || !clientName || !clientEmail) {
                showMessage('Please fill in all fields.', false);
                return;
            }

            const submitBtn = bookingForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            const formData = new URLSearchParams();
            formData.append('action', 'ross_submit_booking');
            formData.append('security', rossBookingData.nonce);
            formData.append('service_id', serviceId);
            formData.append('booking_date', bookingDate);
            formData.append('time_slot', timeSlot);
            formData.append('client_name', clientName);
            formData.append('client_email', clientEmail);

            fetch(rossBookingData.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(res => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm Appointment Booking';

                if (res.success && res.data) {
                    renderConfirmationCard(res.data);
                    bookingForm.reset();
                    bookingForm.style.display = 'none';
                    fetchAvailableSlots();
                } else {
                    showMessage(res.data && res.data.message ? res.data.message : 'Booking failed. Please try again.', false);
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm Appointment Booking';
                showMessage('A network error occurred. Please try again.', false);
            });
        });
    }
});

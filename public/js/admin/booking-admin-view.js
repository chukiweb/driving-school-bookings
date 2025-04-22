jQuery(document).ready(function ($) {

    class bookingAdminView {

        static inputDate = document.querySelector('#createBookingForm input[name="date"]');
        static inputTime = document.querySelector('#createBookingForm input[name="time"]');
        static acceptBookingModal = document.querySelector('#acceptBookingModal');
        static cancelBookingModal = document.querySelector('#cancelBookingModal');

        static init() {
            document.querySelectorAll('.button').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = e.target.dataset.actionId;
                    const btn = e.target.closest('.button');

                    // if (action !== bookingAdminView.lastAction) {
                    //     bookingAdminView.toggleAllContainers(action);
                    //     bookingAdminView.lastAction = action;
                    // }

                    bookingAdminView.handleAction(action, btn);
                });
            });

            bookingAdminView.initBookinFormListener();
            bookingAdminView.addMinDateInputDate();
            bookingAdminView.addMinHourInputHour();
        }

        static handleAction(action, btn) {
            switch (action) {
                case 'accept':
                    bookingAdminView.acceptFormAction(btn.dataset.bookingId);
                    break;
                case 'cancel':
                    bookingAdminView.cancelFormAction(btn.dataset.bookingId);
                    break;
                default:
                    console.error('Acción no reconocida:', action);
            }
        }

        static acceptFormAction(bookingId) {
            const acceptForm = document.querySelector('#acceptBookingForm');
            
            this.fillModalInfo(bookingId, bookingAdminView.acceptBookingModal);
            acceptForm.querySelector('input[name="booking_id"]').value = bookingId;

            bookingAdminView.acceptBookingModal.showModal();
        }

        static cancelFormAction(bookingId) {
            const cancelForm = document.querySelector('#cancelBookingForm');

            cancelForm.querySelector('input[name="booking_id"]').value = bookingId;

            this.fillModalInfo(bookingId, bookingAdminView.cancelBookingModal);
            bookingAdminView.cancelBookingModal.showModal();
        }

        static fillModalInfo(bookingId, modalName) {
            const bookingData = allBookingsData.find(booking => booking.id == bookingId);

            modalName.querySelector('span[class="student-name"]').textContent = bookingData.student.name;
            modalName.querySelector('span[class="teacher-name"]').textContent = bookingData.teacher.name;
            modalName.querySelector('span[class="vehicle-name"]').textContent = bookingData.vehicle.name;
            modalName.querySelector('span[class="booking-date"]').textContent = bookingData.date;
            modalName.querySelector('span[class="booking-time"]').textContent = bookingData.time;
            modalName.querySelector('span[class="booking-status"]').textContent = bookingData.status;
        }

        static initBookinFormListener() {
            document.querySelector('#createBookingForm select[name="student"]').addEventListener('change', function (e) {
                const studentId =  this.value;

                if (studentId) {
                    const studentData = allStudentData.find(student => student.id == studentId);
                    const license = studentData.license_type;
                    var vehicle = '';
                    if (license == 'A') {
                        vehicle = studentData.profesordata.vehicle.motorcycle.title;
                    } else if (license == 'B') {
                        vehicle = studentData.profesordata.vehicle.car.title;
                    }

                    if (studentData) {
                        document.querySelector('#createBookingForm input[name="teacher"]').value = studentData.profesordata.name;
                        document.querySelector('#createBookingForm input[name="license_type"]').value = studentData.license_type;
                        document.querySelector('#createBookingForm input[name="vehicle"]').value = vehicle;
                    }
                }
            });
        }

        static addMinDateInputDate() {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0'); // January is 0!
            const yyyy = today.getFullYear();

            bookingAdminView.inputDate.setAttribute('min', `${yyyy}-${mm}-${dd}`);
        }

        static addMinHourInputHour() {
            const today = new Date();
            const hh = String(today.getHours()).padStart(2, '0');
            const mm = String(today.getMinutes()).padStart(2, '0');

            bookingAdminView.inputTime.setAttribute('min', `${hh}:${mm}`);
        }
    }

    bookingAdminView.init();

    console.log("Alumnos:",allStudentData);
    console.log("Bookings:",allBookingsData);
});

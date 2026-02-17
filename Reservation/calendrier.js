document.addEventListener('DOMContentLoaded', function () {
    var calendarElements = document.querySelectorAll('[id^="calendar-"]');

    calendarElements.forEach(function (calendarEl) {
        var reservedEntries = JSON.parse(calendarEl.getAttribute('data-reserved-dates')) || [];
        var unavailableDates = JSON.parse(calendarEl.getAttribute('data-unavailable-dates')) || [];



        // Transformer les réservations en objet
        var reservationMap = {};

        reservedEntries.forEach(function (entry) {
            var [date, horaire, statut] = entry.split(' ');  // Séparer date, horaire et statut
            if (!reservationMap[date])
            {
                reservationMap[date] = { morning: 'free', afternoon: 'free' };
            }

            if (horaire === 'half_day_morning' || horaire === 'half_day_morning_weekend')
            {
                reservationMap[date].morning = (statut === 'validée') ? 'reserved' : 'pending';
            }
            else if (horaire === 'half_day_afternoon' || horaire === 'half_day_afternoon_weekend')
            {
                reservationMap[date].afternoon = (statut === 'validée') ? 'reserved' : 'pending';
            }
            else if (horaire === 'full_day' || horaire === 'full_day_weekend')
            {
                reservationMap[date].morning = (statut === 'validée') ? 'reserved' : 'pending';
                reservationMap[date].afternoon = (statut === 'validée') ? 'reserved' : 'pending';
            }
        });

        unavailableDates.forEach(function (date) {
            reservationMap[date] = { morning: true, afternoon: true, unavailable: true };
        });
        

        var events = [];
        Object.keys(reservationMap).forEach(function (date) {
            if (reservationMap[date].unavailable)
            {
                events.push({
                    title: 'Indisponible',
                    start: date,
                    color: 'purple'
                });
            }
            else
            {
                // Matin
                if (reservationMap[date].morning === 'reserved')
                {
                    events.push({
                        title: 'Matin',
                        start: date,
                        color: 'rgb(164, 0, 0)',
                        order: 1
                    });
                }
                else if (reservationMap[date].morning === 'pending')
                {
                    events.push({
                        title: 'Matin',
                        start: date,
                        color: 'orange',
                        order: 1
                    });
                }
                else
                {
                    events.push({
                        title: 'Matin',
                        start: date,
                        color: 'green',
                        order: 1
                    });
                }

                // Après-midi
                if (reservationMap[date].afternoon === 'reserved')
                {
                    events.push({
                        title: 'Après-midi',
                        start: date,
                        color: 'rgb(164, 0, 0)',
                        order: 2
                    });
                }
                else if (reservationMap[date].afternoon === 'pending')
                {
                    events.push({
                        title: 'Après-midi',
                        start: date,
                        color: 'orange',
                        order: 2
                    });
                }
                else
                {
                    events.push({
                        title: 'Après-midi',
                        start: date,
                        color: 'green',
                        order: 2
                    });
                }
            }
        });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            selectable: true,
            eventOrder: 'order',
            dateClick: function (info) {
                var selectedDate = info.dateStr;
                var vehicleId = calendarEl.id.replace('calendar-', '');

                // Met la date brute dans l'input hidden
                document.getElementById('date_reservation-' + vehicleId).value = selectedDate;

                // Formatte la date pour affichage
                let dateObj = new Date(selectedDate);
                let options = { day: 'numeric', month: 'long', year: 'numeric' };
                let formattedDate = dateObj.toLocaleDateString('fr-FR', options);

                // Affiche la date formatée dans le <span>
                document.getElementById('display_date-' + vehicleId).textContent = formattedDate;
            },
            events: events
        });

        calendar.render();
    });
});
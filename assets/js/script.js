(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function startOfWeek(date) {
        const day = (date.getDay() + 6) % 7; // Monday = 0
        const result = new Date(date);
        result.setDate(result.getDate() - day);
        result.setHours(0, 0, 0, 0);
        return result;
    }

    function endOfWeek(date) {
        const result = startOfWeek(date);
        result.setDate(result.getDate() + 6);
        result.setHours(23, 59, 59, 999);
        return result;
    }

    function addDays(date, days) {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }

    function isSameDay(a, b) {
        return (
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );
    }

    function formatDateItalian(date) {
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function parseDate(value) {
        if (!value) return null;
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return new Date(value + 'T00:00:00');
        }
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
            const [day, month, year] = value.split('/');
            return new Date(`${year}-${month}-${day}T00:00:00`);
        }
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    ready(function () {
        const app = document.getElementById('bsn-app');
        if (!app) return;

        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                tabButtons.forEach((btn) => btn.classList.remove('active'));
                tabContents.forEach((content) => content.classList.remove('active'));
                button.classList.add('active');
                const target = document.getElementById(button.dataset.tab);
                if (target) {
                    target.classList.add('active');
                }
            });
        });

        const calendarGrid = document.getElementById('bsn-calendar-grid');
        const calendarDay = document.getElementById('bsn-calendar-day');
        const calendarTitle = document.getElementById('bsn-calendar-title');
        const prevBtn = document.getElementById('bsn-calendar-prev');
        const nextBtn = document.getElementById('bsn-calendar-next');
        const searchInput = document.getElementById('bsn-calendar-search');
        const viewButtons = document.querySelectorAll('.bsn-calendar-view');

        if (!calendarGrid || !calendarTitle || !prevBtn || !nextBtn || !searchInput) {
            return;
        }

        const calendarState = {
            view: 'month',
            currentDate: new Date(),
            rentals: [],
            filter: ''
        };

        function setView(view) {
            calendarState.view = view;
            viewButtons.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.view === view);
            });
            renderCalendar();
        }

        function filterRentals() {
            const term = calendarState.filter.trim().toLowerCase();
            if (!term) {
                return calendarState.rentals;
            }
            return calendarState.rentals.filter((rental) => {
                return (
                    rental.id.toLowerCase().includes(term) ||
                    rental.cliente_nome.toLowerCase().includes(term) ||
                    rental.articoli_riassunto.toLowerCase().includes(term)
                );
            });
        }

        function renderDayView(date, rentals) {
            calendarGrid.innerHTML = '';
            calendarDay.hidden = false;
            calendarDay.innerHTML = '';
            const title = document.createElement('h3');
            title.textContent = `Giorno ${formatDateItalian(date)}`;
            calendarDay.appendChild(title);

            const list = document.createElement('ul');
            list.className = 'bsn-calendar-day-list';

            const dayRentals = rentals.filter((rental) => {
                return rental.start && rental.end && rental.start <= date && rental.end >= date;
            });

            if (!dayRentals.length) {
                const empty = document.createElement('p');
                empty.textContent = 'Nessun noleggio per questa data.';
                calendarDay.appendChild(empty);
                return;
            }

            dayRentals.forEach((rental) => {
                const item = document.createElement('li');
                item.className = `bsn-calendar-day-item bsn-calendar-event--${rental.stato}`;
                const link = document.createElement('a');
                link.href = `/ispeziona/?id=${encodeURIComponent(rental.id)}`;
                link.textContent = `${rental.cliente_nome} (${rental.id})`;
                item.appendChild(link);
                const info = document.createElement('div');
                info.textContent = `${formatDateItalian(rental.start)} → ${formatDateItalian(rental.end)}`;
                item.appendChild(info);
                if (rental.articoli_riassunto && rental.articoli_riassunto !== '-') {
                    const articoli = document.createElement('div');
                    articoli.textContent = rental.articoli_riassunto;
                    item.appendChild(articoli);
                }
                list.appendChild(item);
            });

            calendarDay.appendChild(list);
        }

        function renderCalendar() {
            const rentals = filterRentals();
            const current = new Date(calendarState.currentDate);
            calendarDay.hidden = true;

            if (calendarState.view === 'day') {
                calendarTitle.textContent = formatDateItalian(current);
                renderDayView(current, rentals);
                return;
            }

            calendarGrid.innerHTML = '';

            let rangeStart;
            let rangeEnd;
            if (calendarState.view === 'month') {
                rangeStart = startOfWeek(new Date(current.getFullYear(), current.getMonth(), 1));
                rangeEnd = endOfWeek(new Date(current.getFullYear(), current.getMonth() + 1, 0));
                calendarTitle.textContent = current.toLocaleDateString('it-IT', {
                    month: 'long',
                    year: 'numeric'
                });
            } else {
                rangeStart = startOfWeek(current);
                rangeEnd = endOfWeek(current);
                calendarTitle.textContent = `Settimana ${formatDateItalian(rangeStart)} → ${formatDateItalian(rangeEnd)}`;
            }

            const weeks = [];
            let cursor = new Date(rangeStart);
            cursor.setHours(0, 0, 0, 0);
            while (cursor <= rangeEnd) {
                weeks.push(new Date(cursor));
                cursor = addDays(cursor, 7);
            }

            weeks.forEach((weekStart) => {
                const weekEnd = endOfWeek(weekStart);
                const weekWrapper = document.createElement('div');
                weekWrapper.className = 'bsn-calendar-week-row';

                const daysRow = document.createElement('div');
                daysRow.className = 'bsn-calendar-week';

                for (let i = 0; i < 7; i += 1) {
                    const day = addDays(weekStart, i);
                    const cell = document.createElement('div');
                    cell.className = 'bsn-calendar-day-cell';
                    if (calendarState.view === 'month' && day.getMonth() !== current.getMonth()) {
                        cell.classList.add('outside');
                    }
                    const number = document.createElement('div');
                    number.className = 'bsn-calendar-day-number';
                    number.textContent = day.getDate();
                    cell.appendChild(number);
                    daysRow.appendChild(cell);
                }

                const eventsRow = document.createElement('div');
                eventsRow.className = 'bsn-calendar-week-events';

                const weekRentals = rentals.filter((rental) => {
                    return rental.start && rental.end && rental.start <= weekEnd && rental.end >= weekStart;
                });

                const segments = weekRentals.map((rental) => {
                    const segStart = rental.start < weekStart ? weekStart : rental.start;
                    const segEnd = rental.end > weekEnd ? weekEnd : rental.end;
                    const startIndex = Math.floor((segStart - weekStart) / 86400000) + 1;
                    const endIndex = Math.floor((segEnd - weekStart) / 86400000) + 1;
                    return {
                        rental,
                        startIndex,
                        endIndex
                    };
                });

                segments.sort((a, b) => {
                    if (a.startIndex === b.startIndex) {
                        return a.endIndex - b.endIndex;
                    }
                    return a.startIndex - b.startIndex;
                });

                const lanes = [];
                segments.forEach((segment) => {
                    let placed = false;
                    for (let laneIndex = 0; laneIndex < lanes.length; laneIndex += 1) {
                        const lane = lanes[laneIndex];
                        const overlaps = lane.some((existing) => {
                            return segment.startIndex <= existing.endIndex && segment.endIndex >= existing.startIndex;
                        });
                        if (!overlaps) {
                            lane.push(segment);
                            segment.lane = laneIndex + 1;
                            placed = true;
                            break;
                        }
                    }
                    if (!placed) {
                        lanes.push([segment]);
                        segment.lane = lanes.length;
                    }
                });

                segments.forEach((segment) => {
                    const event = document.createElement('div');
                    event.className = `bsn-calendar-event bsn-calendar-event--${segment.rental.stato}`;
                    event.style.gridColumn = `${segment.startIndex} / ${segment.endIndex + 1}`;
                    event.style.gridRow = `${segment.lane}`;
                    event.textContent = segment.rental.cliente_nome;
                    event.title = `${segment.rental.id} • ${formatDateItalian(segment.rental.start)} → ${formatDateItalian(segment.rental.end)}`;
                    event.addEventListener('click', () => {
                        window.location.href = `/ispeziona/?id=${encodeURIComponent(segment.rental.id)}`;
                    });
                    eventsRow.appendChild(event);
                });

                weekWrapper.appendChild(daysRow);
                weekWrapper.appendChild(eventsRow);
                calendarGrid.appendChild(weekWrapper);
            });
        }

        function loadRentals() {
            calendarGrid.innerHTML = '<p>Caricamento calendario...</p>';
            const headers = {};
            if (window.BSN_API && BSN_API.nonce) {
                headers['X-WP-Nonce'] = BSN_API.nonce;
            }
            const url = window.BSN_API ? `${BSN_API.root}noleggi` : '/wp-json/bsn/v1/noleggi';
            fetch(url, { headers })
                .then((response) => response.json())
                .then((data) => {
                    const rentals = (data.noleggi || []).map((item) => {
                        const start = parseDate(item.data_inizio_raw || item.data_da);
                        const end = parseDate(item.data_fine_raw || item.data_a);
                        if (!start || !end) {
                            return null;
                        }
                        start.setHours(0, 0, 0, 0);
                        end.setHours(0, 0, 0, 0);
                        return {
                            id: item.id || '',
                            cliente_nome: item.cliente_nome || 'Cliente',
                            articoli_riassunto: item.articoli_riassunto || '-',
                            stato: item.stato || 'bozza',
                            start,
                            end
                        };
                    }).filter(Boolean);
                    calendarState.rentals = rentals;
                    renderCalendar();
                })
                .catch(() => {
                    calendarGrid.innerHTML = '<p>Errore nel caricamento del calendario.</p>';
                });
        }

        prevBtn.addEventListener('click', () => {
            const current = calendarState.currentDate;
            if (calendarState.view === 'month') {
                calendarState.currentDate = new Date(current.getFullYear(), current.getMonth() - 1, 1);
            } else if (calendarState.view === 'week') {
                calendarState.currentDate = addDays(current, -7);
            } else {
                calendarState.currentDate = addDays(current, -1);
            }
            renderCalendar();
        });

        nextBtn.addEventListener('click', () => {
            const current = calendarState.currentDate;
            if (calendarState.view === 'month') {
                calendarState.currentDate = new Date(current.getFullYear(), current.getMonth() + 1, 1);
            } else if (calendarState.view === 'week') {
                calendarState.currentDate = addDays(current, 7);
            } else {
                calendarState.currentDate = addDays(current, 1);
            }
            renderCalendar();
        });

        viewButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                setView(btn.dataset.view);
            });
        });

        searchInput.addEventListener('input', (event) => {
            calendarState.filter = event.target.value || '';
            renderCalendar();
        });

        loadRentals();
    });
})();

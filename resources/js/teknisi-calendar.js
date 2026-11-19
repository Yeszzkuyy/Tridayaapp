import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

function initCalendar() {
    const el = document.getElementById('teknisi-calendar');
    if (!el) return;
    if (window.__teknisiCalendar && window.__teknisiCalendar.el === el) return;
    if (window.__teknisiCalendar) {
        window.__teknisiCalendar.instance.destroy();
        window.__teknisiCalendar = null;
    }

    const eventsUrl = el.dataset.eventsUrl;

    const palette = [
        '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
        '#0d9488', '#db2777', '#4f46e5', '#ea580c', '#0891b2',
    ];
    const colorForTechnician = (id) => {
        if (!id) return '#475569';
        let h = 0;
        for (const c of String(id)) h = (h * 31 + c.charCodeAt(0)) >>> 0;
        return palette[h % palette.length];
    };
    const fmtTime = (d) => new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false,
    }).format(d);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        timeZone: 'Asia/Jakarta',
        initialView: 'dayGridMonth',
        firstDay: 1,
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay addButton',
        },
        customButtons: {
            addButton: {
                text: '+ Add Schedule',
                click: () => window.teknisiSchedule.openCreate(new Date()),
            },
        },
        events: [
            (info, successCallback, failureCallback) => {
                const params = new URLSearchParams({
                    start: info.startStr,
                    end: info.endStr,
                    search: (document.getElementById('filter-search')?.value || '').trim(),
                });
                fetch(`${eventsUrl}?${params.toString()}`)
                    .then((r) => r.json())
                    .then((events) => successCallback(events.map((e) => ({
                        ...e,
                        backgroundColor: colorForTechnician(e.technician_user_id),
                        borderColor: colorForTechnician(e.technician_user_id),
                        textColor: '#ffffff',
                    }))))
                    .catch(failureCallback);
            },
            {
                url: '/teknisi/jadwal/google-events',
                method: 'GET',
                failure: function (err) {
                    console.error('Failed to load Google Calendar', err);
                },
            },
        ],
        eventDisplay: 'block',
        eventContent: (arg) => {
            const ev = arg.event;
            const start = fmtTime(ev.start);
            const end = ev.end ? fmtTime(ev.end) : start;
            const tech = ev.extendedProps.technician ? `<div class="fc-event-line">Engineer: ${esc(ev.extendedProps.technician)}</div>` : '';
            const cust = ev.extendedProps.customer ? `<div class="fc-event-line">Customer: ${esc(ev.extendedProps.customer)}</div>` : '';
            return {
                html: `<div class="fc-event-card">
                            <div class="fc-event-time">${start} - ${end}</div>
                            <div class="fc-event-title-text">${esc(ev.title)}</div>
                            ${cust}${tech}
                        </div>`,
            };
        },
        dateClick: (info) => window.teknisiSchedule.openCreate(info.date),
        eventClick: (info) => window.teknisiSchedule.openDetail(info.event),
        eventDidMount: (info) => {
            const el = info.el;
            const sync = info.event.extendedProps.sync_status;
            if (sync === 'error') el.style.border = '1.5px dashed #dc2626';
            if (sync === 'not_connected') el.classList.add('fc-event-not-connected');
        },
        dayMaxEvents: 3,
        moreLinkContent: (arg) => `+${arg.num} more`,
    });

    calendar.render();

    window.__teknisiCalendar = { el, instance: calendar };
    window.teknisiCalendar = { calendar };
}

document.addEventListener('DOMContentLoaded', initCalendar);
document.addEventListener('livewire:navigated', initCalendar);

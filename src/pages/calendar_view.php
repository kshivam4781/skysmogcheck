<?php
// Set page title
$page_title = "Calendar";

// Additional CSS for calendar
$additional_css = '
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
<style>
    /* Main Container Styling */
    .main-content {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: calc(100vh - 60px);
        padding: 20px;
    }
    
    .calendar-container {
        width: 100%;
        max-width: 1200px;
        padding: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin: 0 auto;
        transition: all 0.3s ease;
    }
    
    /* Calendar Header Styling */
    .fc {
        max-width: 100%;
        margin: 0 auto;
    }
    
    .fc .fc-toolbar {
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px !important;
        padding: 0 10px;
        justify-content: center;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.5em;
        font-weight: 600;
        color: #2c3e50;
        text-align: center;
        width: 100%;
        margin-bottom: 15px;
    }
    
    .fc .fc-toolbar-chunk {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    
    .fc .fc-button {
        padding: 0.5em 1em;
        font-size: 0.95em;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
        text-transform: capitalize;
    }
    
    .fc .fc-button-primary {
        background-color: #4a90e2;
        border-color: #4a90e2;
    }
    
    .fc .fc-button-primary:hover {
        background-color: #357abd;
        border-color: #357abd;
        transform: translateY(-1px);
    }
    
    .fc .fc-button-primary:not(:disabled):active,
    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background-color: #357abd;
        border-color: #357abd;
    }
    
    /* Calendar Grid Styling */
    .fc .fc-view-harness {
        background: white;
        border-radius: 8px;
    }
    
    .fc .fc-daygrid-day {
        min-height: 100px;
        transition: background-color 0.2s ease;
    }
    
    .fc .fc-daygrid-day:hover {
        background-color: #f8f9fa;
    }
    
    .fc .fc-daygrid-day-number {
        font-size: 0.95em;
        padding: 8px;
        color: #495057;
    }
    
    .fc .fc-day-today {
        background-color: #f0f7ff !important;
    }
    
    /* Event Styling */
    .fc-event {
        cursor: pointer;
        font-size: 0.9em;
        padding: 4px 8px;
        border-radius: 4px;
        border: none;
        margin: 2px 0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .fc-event:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .fc-event-main {
        padding: 2px 4px;
    }
    
    /* Event Colors */
    .fc-event.pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .fc-event.confirmed {
        background-color: #d4edda;
        color: #155724;
    }
    
    .fc-event.completed {
        background-color: #cce5ff;
        color: #004085;
    }
    
    .fc-event.cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    /* Modal Styling */
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 5px 25px rgba(0,0,0,0.1);
    }
    
    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        border-radius: 12px 12px 0 0;
        padding: 1.2rem;
    }
    
    .modal-title {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .modal-body {
        padding: 1.5rem;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    /* Vehicle Card Styling */
    .vehicle-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background-color: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .vehicle-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .vehicle-card h6 {
        margin-bottom: 15px;
        color: #2c3e50;
        font-weight: 600;
    }
    
    .vehicle-card p {
        margin-bottom: 8px;
        color: #495057;
    }
    
    /* Section Styling */
    .section {
        padding: 20px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 20px;
        background-color: #fff;
    }
    
    .section-title {
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 600;
        font-size: 1.1em;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 1200px) {
        .calendar-container {
            max-width: 95%;
            padding: 20px;
        }
    }
    
    @media (max-width: 768px) {
        .main-content {
            padding: 10px;
        }
        
        .calendar-container {
            padding: 15px;
            margin: 10px;
        }
        
        .fc .fc-toolbar {
            flex-direction: column;
            align-items: center;
        }
        
        .fc .fc-toolbar-title {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .fc .fc-button {
            padding: 0.4em 0.8em;
            font-size: 0.9em;
        }
        
        .fc .fc-toolbar-chunk {
            flex-wrap: wrap;
        }
    }
</style>
';

// Include layout
require_once '../layouts/layout.php';

// Check if user has consultant access
if (!isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    $_SESSION['error_message'] = "Access restricted. Please contact your administrator.";
    header("Location: login.php");
    exit();
}
?>

<!-- Calendar Container -->
<div class="calendar-container">
    <div id="calendar"></div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Additional JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'get_appointments.php',
        eventClick: function(info) {
            // Load appointment details
            fetch('get_appointment_details.php?id=' + info.event.id)
                .then(response => response.text())
                .then(html => {
                    document.querySelector('#appointmentModal .modal-body').innerHTML = html;
                    var modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                    modal.show();
                });
        },
        eventDidMount: function(info) {
            // Add tooltips
            $(info.el).tooltip({
                title: info.event.title,
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
            
            // Add status-based classes
            if (info.event.extendedProps.status) {
                info.el.classList.add(info.event.extendedProps.status.toLowerCase());
            }
        },
        height: 'auto',
        aspectRatio: 1.5,
        expandRows: true,
        stickyHeaderDates: true,
        dayMaxEvents: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        }
    });
    calendar.render();
    
    // Handle window resize
    window.addEventListener('resize', function() {
        calendar.updateSize();
    });
});
</script> 
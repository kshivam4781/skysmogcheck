<?php
require_once '../config/db_connection.php';

// Clean Truck Check Reminder Template
$cleanTruckTemplate = [
    'type' => 'clean_truck',
    'title' => 'Clean Truck Check Reminder',
    'subject' => 'Your Clean Truck Check is Due Soon - {company_name}',
    'html_content' => '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; }
            .details { background-color: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Clean Truck Check Reminder</h1>
            </div>
            <div class="content">
                <p>Dear {client_name},</p>
                
                <p>This is a friendly reminder that your Clean Truck Check is due soon for the following vehicle:</p>
                
                <div class="details">
                    <p><strong>Company:</strong> {company_name}</p>
                    <p><strong>Vehicle:</strong> {vehicle_year} {vehicle_make} ({plate_number})</p>
                    <p><strong>Due Date:</strong> {due_date}</p>
                </div>

                <p>To ensure compliance and avoid any penalties, please schedule your Clean Truck Check as soon as possible.</p>

                <p style="text-align: center; margin: 30px 0;">
                    <a href="{schedule_link}" class="button">Schedule Now</a>
                </p>

                <p>If you have already scheduled your appointment, please disregard this reminder.</p>

                <p>For any questions or assistance, please contact us at:</p>
                <p>Phone: {company_phone}<br>
                Email: {company_email}</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; {current_year} Sky Smoke Check LLC. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>'
];

// Smog Test Reminder Template
$smogTestTemplate = [
    'type' => 'smog_test',
    'title' => 'Smog Test Reminder',
    'subject' => 'Your Smog Test is Due Soon - {company_name}',
    'html_content' => '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .details { background-color: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .warning { color: #dc3545; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Smog Test Reminder</h1>
            </div>
            <div class="content">
                <p>Dear {client_name},</p>
                
                <p>This is a reminder that your Smog Test is due soon for the following vehicle:</p>
                
                <div class="details">
                    <p><strong>Company:</strong> {company_name}</p>
                    <p><strong>Vehicle:</strong> {vehicle_year} {vehicle_make} ({plate_number})</p>
                    <p><strong>Due Date:</strong> {due_date}</p>
                    <p class="warning">Expiration Date: {expiration_date}</p>
                </div>

                <p>To maintain compliance with state regulations and avoid any penalties, please schedule your Smog Test as soon as possible.</p>

                <p style="text-align: center; margin: 30px 0;">
                    <a href="{schedule_link}" class="button">Schedule Smog Test</a>
                </p>

                <p>If you have already scheduled your appointment, please disregard this reminder.</p>

                <p>For any questions or assistance, please contact us at:</p>
                <p>Phone: {company_phone}<br>
                Email: {company_email}</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; {current_year} Sky Smoke Check LLC. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>'
];

// Appointment Reminder Template
$appointmentTemplate = [
    'type' => 'appointment',
    'title' => 'Appointment Reminder',
    'subject' => 'Upcoming Appointment Reminder - {company_name}',
    'html_content' => '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #6f42c1; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #6f42c1; color: white; text-decoration: none; border-radius: 5px; }
            .details { background-color: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .location { margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Appointment Reminder</h1>
            </div>
            <div class="content">
                <p>Dear {client_name},</p>
                
                <p>This is a reminder of your upcoming appointment:</p>
                
                <div class="details">
                    <p><strong>Service Type:</strong> {service_type}</p>
                    <p><strong>Date:</strong> {appointment_date}</p>
                    <p><strong>Time:</strong> {appointment_time}</p>
                    <p><strong>Vehicle:</strong> {vehicle_year} {vehicle_make} ({plate_number})</p>
                </div>

                <div class="location">
                    <h3>Location Details</h3>
                    <p><strong>Address:</strong><br>
                    {location_address}</p>
                    <p><strong>Phone:</strong> {location_phone}</p>
                </div>

                <p style="text-align: center; margin: 30px 0;">
                    <a href="{reschedule_link}" class="button">Reschedule Appointment</a>
                </p>

                <p>If you need to cancel or reschedule your appointment, please do so at least 24 hours in advance.</p>

                <p>For any questions or assistance, please contact us at:</p>
                <p>Phone: {company_phone}<br>
                Email: {company_email}</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; {current_year} Sky Smoke Check LLC. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>'
];

// Custom Reminder Template
$customTemplate = [
    'type' => 'custom',
    'title' => 'Custom Reminder',
    'subject' => '{custom_subject}',
    'html_content' => '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; }
            .details { background-color: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>{custom_title}</h1>
            </div>
            <div class="content">
                <p>Dear {client_name},</p>
                
                {custom_message}
                
                <div class="details">
                    <p><strong>Company:</strong> {company_name}</p>
                    <p><strong>Vehicle:</strong> {vehicle_year} {vehicle_make} ({plate_number})</p>
                    <p><strong>Due Date:</strong> {due_date}</p>
                </div>

                {custom_action_button}

                <p>For any questions or assistance, please contact us at:</p>
                <p>Phone: {company_phone}<br>
                Email: {company_email}</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; {current_year} Sky Smoke Check LLC. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>'
];

// Insert templates into database
$templates = [$cleanTruckTemplate, $smogTestTemplate, $appointmentTemplate, $customTemplate];

$stmt = $conn->prepare("INSERT INTO reminder_messages (type, title, subject, html_content) VALUES (?, ?, ?, ?)");

foreach ($templates as $template) {
    $stmt->bind_param("ssss", 
        $template['type'],
        $template['title'],
        $template['subject'],
        $template['html_content']
    );
    $stmt->execute();
}

echo "Reminder message templates have been created successfully!";
?> 
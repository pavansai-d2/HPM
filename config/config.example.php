<?php
// Copy this file to config.php and enter your hosting/database credentials.
return [
    'db_host' => 'localhost',
    'db_name' => 'hpm_catalogue',
    'db_user' => 'root',
    'db_port' => 3306,
    'db_pass' => 'YOUR_DB_PASSWORD',
    'site_url' => 'https://haripalletmachinery.com',
    'whatsapp_number' => '971500000000', // Replace with HPM's WhatsApp number, digits only, including country code.
    'mail_to' => 'info@haripalletmachinery.com',
    'mail_from' => 'website@haripalletmachinery.com', // Must be a mailbox/domain authorised by your hosting provider.
    'google_apps_script_url' => '', // Optional deployed Google Apps Script Web App URL for Google Sheets lead capture.
    'recaptcha_site_key' => 'YOUR_RECAPTCHA_V2_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_V2_SECRET_KEY',
    'session_name' => 'HPM_ADMIN'
];

<?php
declare(strict_types=1);

/*
 * HPM SMTP configuration.
 * Replace the placeholders with the SMTP details supplied by your email host.
 * Do not share this file publicly or commit real credentials to Git.
 */
return [
    'host'       => 'YOUR_SMTP_HOST',
    'username'   => 'info@haripalletmachinery.com',
    'password'   => 'YOUR_EMAIL_ACCOUNT_PASSWORD',
    'port'       => 587,
    'encryption' => 'tls', // Usually tls for 587 or ssl for 465.
    'from_email' => 'info@haripalletmachinery.com',
    'from_name'  => 'Hari Pallet Machinery Website',
    'to_email'   => 'info@haripalletmachinery.com',
    'to_name'    => 'Hari Pallet Machinery',
];

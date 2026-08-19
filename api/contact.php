<?php
/* Contact form endpoint — POST name/email/message, sends an email to the
   team inbox (config: contact_to). See api/config.example.php. */

require __DIR__ . '/_lib.php';

require_post();
$cfg = load_config();
honeypot_check();
rate_limit('contact', 10, 3600);

$name    = field('name', 200);
$email   = field('email', 254);
$message = field('message', 5000, true);

$errors = [];
if ($name === '')    $errors['name'] = 'Required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
if ($message === '') $errors['message'] = 'Required';
if ($errors) {
    respond(422, ['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $errors]);
}

$body = "New contact form message — alumoimpact.ca\n\n"
      . "Name:  $name\n"
      . "Email: $email\n\n"
      . "Message:\n$message\n";

$sent = send_mail($cfg, $cfg['contact_to'], "Contact form — $name", $body, $email);

if (!$sent) {
    respond(500, ['ok' => false, 'error' => 'The message could not be sent. Please try again later.']);
}
respond(200, ['ok' => true]);

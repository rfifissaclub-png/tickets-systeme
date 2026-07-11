<?php

return [

    'validation_failed' => 'The given data was invalid.',

    'auth' => [
        'login_success' => 'Logged in successfully.',
        'logout_success' => 'Logged out successfully.',
        'credentials_invalid' => 'These credentials do not match our records.',
        'password_reset_link_sent' => 'If that account exists, a password reset link has been sent.',
        'password_reset_success' => 'Password reset successfully.',
        'email_already_verified' => 'Email already verified.',
        'verification_link_sent' => 'Verification link sent.',
        'email_verified_success' => 'Email verified successfully.',
        'invalid_verification_link' => 'Invalid or expired verification link.',
    ],

    'tickets' => [
        'created' => 'Ticket created.',
        'updated' => 'Ticket updated.',
        'deleted' => 'Ticket deleted.',
        'status_updated' => 'Ticket status updated.',
        'assigned' => 'Ticket assigned.',
        'pulled' => 'Ticket assigned to you.',
        'comment_added' => 'Comment added.',
        'read_only_resolved' => 'Resolved tickets are read-only; change the status to reopen before editing.',
        'read_only_closed' => 'Closed tickets cannot be modified.',
        'already_assigned' => 'This ticket has already been claimed by another agent.',
        'invalid_transition' => 'Cannot transition a ticket from ":from" to ":to".',
    ],

    'ticket_types' => [
        'created' => 'Ticket type created.',
        'updated' => 'Ticket type updated.',
        'deleted' => 'Ticket type deleted.',
    ],

    'attachments' => [
        'uploaded' => 'File uploaded.',
        'deleted' => 'Attachment deleted.',
    ],

    'notifications' => [
        'all_marked_read' => 'All notifications marked as read.',
    ],

    'http' => [
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'This action is unauthorized.',
        'not_found' => 'The requested resource was not found.',
        'rate_limited' => 'Too many requests.',
        'http_error' => 'An error occurred.',
        'server_error' => 'Server error.',
    ],

];

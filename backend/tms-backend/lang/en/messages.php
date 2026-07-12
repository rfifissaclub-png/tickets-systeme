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
        'not_qualified' => 'You are not qualified to pull this ticket type. Ask an administrator to grant you this skill.',
    ],

    'ticket_types' => [
        'created' => 'Ticket type created.',
        'updated' => 'Ticket type updated.',
        'deleted' => 'Ticket type deleted.',
    ],

    'tenants' => [
        'created' => 'Entity created.',
        'updated' => 'Entity updated.',
        'deleted' => 'Entity suspended.',
    ],

    'users' => [
        'created' => 'User created.',
        'updated' => 'User updated.',
        'deleted' => 'User deactivated.',
        'roles_updated' => 'Roles updated.',
        'skills_updated' => 'Skills updated.',
        'only_super_admin_grants_super_admin' => 'Only a Super Admin may grant the Super Admin role.',
        'super_admin_role_requires_no_tenant' => 'The Super Admin role can only be granted to a platform-level account with no tenant.',
        'skills_must_match_tenant' => 'One or more ticket types do not belong to this user\'s tenant.',
    ],

    'attachments' => [
        'uploaded' => 'File uploaded.',
        'deleted' => 'Attachment deleted.',
        'infected' => 'This file was rejected because it appears to contain malware.',
        'scan_unavailable' => 'File could not be scanned for viruses right now. Please try again shortly.',
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

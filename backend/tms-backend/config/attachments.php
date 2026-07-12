<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | In kilobytes, matching the unit Laravel's own `max:` validation rule
    | expects. Ops-configurable via .env rather than a PHP constant, so
    | raising or lowering the limit doesn't require a code change/deploy.
    |
    */

    'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 25 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Antivirus Scanning
    |--------------------------------------------------------------------------
    |
    | Optional but recommended (cahier des charges §"stockage privé"):
    | every uploaded file is streamed to a ClamAV daemon (clamd) over its
    | INSTREAM protocol before being persisted to permanent storage.
    |
    | Disabled by default: a fresh environment has no clamd running, and
    | failing every upload with "scanner unreachable" until someone deploys
    | ClamAV would be a worse default than simply not scanning. Enable via
    | ATTACHMENTS_AV_ENABLED once clamd is actually deployed alongside the
    | app (see infra/docker-compose.yml).
    |
    | fail_closed governs what happens if scanning is enabled but clamd
    | itself is unreachable/errors mid-scan: true (default) rejects the
    | upload rather than silently storing an unscanned file, on the
    | reasoning that a security control which fails open isn't actually a
    | security control. Set to false only if upload availability matters
    | more than this guarantee for your deployment.
    |
    */

    'antivirus' => [
        'enabled' => (bool) env('ATTACHMENTS_AV_ENABLED', false),
        'host' => env('ATTACHMENTS_AV_HOST', '127.0.0.1'),
        'port' => (int) env('ATTACHMENTS_AV_PORT', 3310),
        'timeout' => (int) env('ATTACHMENTS_AV_TIMEOUT', 10),
        'fail_closed' => (bool) env('ATTACHMENTS_AV_FAIL_CLOSED', true),
    ],

];

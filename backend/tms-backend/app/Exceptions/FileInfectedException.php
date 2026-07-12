<?php

namespace App\Exceptions;

use RuntimeException;

class FileInfectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('messages.attachments.infected'));
    }
}

<?php

namespace App\Exceptions;

use Exception;

class GiftNotFoundException extends Exception
{
    public string $userMessage;

    public function __construct(string $userMessage = 'This gift code is not valid.')
    {
        parent::__construct($userMessage);
        $this->userMessage = $userMessage;
    }
}

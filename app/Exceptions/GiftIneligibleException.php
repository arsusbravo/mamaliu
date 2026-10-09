<?php

namespace App\Exceptions;

use Exception;

class GiftIneligibleException extends Exception
{
    public string $userMessage;

    public function __construct(string $userMessage)
    {
        parent::__construct($userMessage);
        $this->userMessage = $userMessage;
    }
}

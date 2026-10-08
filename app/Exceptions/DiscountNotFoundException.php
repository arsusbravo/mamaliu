<?php

namespace App\Exceptions;

use Exception;

class DiscountNotFoundException extends Exception
{
    public string $userMessage;

    public function __construct(string $userMessage = 'This discount code is not valid.')
    {
        parent::__construct($userMessage);
        $this->userMessage = $userMessage;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmailContentController extends Controller
{
    public function index()
    {
        return inertia('emails/Index');
    }

}

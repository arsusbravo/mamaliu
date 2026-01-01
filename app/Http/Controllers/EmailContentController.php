<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmailContentController extends Controller
{
    public function index()
    {
        return inertia('emails/Index');
    }

    public function show($id)
    {
        return inertia('emails/Show', ['id' => $id]);
    }
}

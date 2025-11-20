<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class MapController extends Controller
{
    public function index(): View
    {
        return view('map');
    }
}
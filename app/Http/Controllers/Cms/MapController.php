<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;

class MapController extends Controller
{
    public function mapInput()
    {
        return view('cms.map-input');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getStoreSettings()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }
}

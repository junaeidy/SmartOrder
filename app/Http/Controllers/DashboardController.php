<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function owner()
    {
        return Inertia::render('Owner/Dashboard');
    }

    public function kasir()
    {
        return Inertia::render('Kasir/Dashboard');
    }

    public function karyawan()
    {
        return Inertia::render('Karyawan/Dashboard');
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\PrototypeDashboard;
use Illuminate\View\View;

class PrototypeDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', PrototypeDashboard::data());
    }
}

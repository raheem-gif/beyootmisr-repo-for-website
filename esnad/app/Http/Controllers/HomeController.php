<?php

namespace App\Http\Controllers;

use App\Models\Governorate;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $governorates = Cache::remember('governorates.all', 3600, fn () => Governorate::query()->orderBy('sort')->get());

        // Platform numbers come from the database, or the band is not shown
        // (section 16). Lawyer counts arrive with phase 2.
        $numbers = [
            'verified_lawyers' => 0,
            'governorates' => $governorates->where('is_active', true)->count(),
            'median_response' => null,
        ];

        return view('home', [
            'governorates' => $governorates,
            'numbers' => $numbers,
            'areas' => array_keys(__('site.areas')),
        ]);
    }
}

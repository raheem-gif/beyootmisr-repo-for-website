<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use Illuminate\View\View;

/**
 * Search, booking and request-now open in later phases behind feature
 * flags. Until then the links lead here, with one line on what happened
 * and one on what to do next.
 */
class NotOpenController extends Controller
{
    public function search(): View
    {
        return $this->render('search');
    }

    public function request(): View
    {
        return $this->render('request');
    }

    private function render(string $feature): View
    {
        abort_if(FeatureFlag::query()->where('key', $feature)->value('enabled'), 404);

        return view('not-open', ['feature' => $feature]);
    }
}

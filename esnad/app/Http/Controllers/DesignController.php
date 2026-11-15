<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Component gallery for design review. Not available in production. */
class DesignController extends Controller
{
    public function __invoke(): View
    {
        abort_if(app()->isProduction(), 404);

        return view('design');
    }
}

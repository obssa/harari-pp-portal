<?php

namespace App\Http\Controllers\Public;

use App\Domain\Homepage\Support\HomepageLayout;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The homepage, as arranged in the homepage builder (PLAN.md §4, ADR-0019).
 */
final class HomeController extends Controller
{
    public function __invoke(HomepageLayout $layout): View
    {
        return view('pages.home', ['sections' => $layout->forLocale(app()->getLocale())]);
    }
}

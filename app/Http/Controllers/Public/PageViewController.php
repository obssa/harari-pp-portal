<?php

namespace App\Http\Controllers\Public;

use App\Domain\Analytics\Actions\RecordPageView;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the page's view beacon (ADR-0040). It answers 204 whatever
 * happens, so the page never waits on it, and sets no cookie.
 */
final class PageViewController extends Controller
{
    public function __invoke(Request $request, RecordPageView $record): Response
    {
        $body = (string) $request->getContent();
        $data = strlen($body) <= 1024 ? json_decode($body, true) : null;

        if (is_array($data)) {
            $record->handle(
                $data,
                $request->userAgent(),
                $request->headers->get('CF-IPCountry'),
                $request->headers->get('DNT') ?? ($request->headers->get('Sec-GPC') === '1' ? '1' : null),
            );
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}

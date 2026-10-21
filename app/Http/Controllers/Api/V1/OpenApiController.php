<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The OpenAPI 3.1 description of the API (ADR-0056), readable without a key
 * so developers can try the API in their tools.
 */
final class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var array<string, mixed> $document */
        $document = json_decode((string) file_get_contents(resource_path('api/openapi-v1.json')), true, flags: JSON_THROW_ON_ERROR);
        $document['servers'] = [['url' => url('/api/v1')]];

        return response()->json($document, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->setPublic()
            ->setMaxAge(3600);
    }
}

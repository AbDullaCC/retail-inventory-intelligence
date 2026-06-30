<?php

namespace App\Http\Controllers;

use App\Support\OpenApi\OpenApiSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Serves the OpenAPI document and the Swagger UI page.
 */
final class DocsController extends Controller
{
    public function spec(): JsonResponse
    {
        return response()->json(OpenApiSpec::build(url('/')));
    }

    public function ui(): View
    {
        return view('docs', ['specUrl' => url('/docs/openapi.json')]);
    }
}

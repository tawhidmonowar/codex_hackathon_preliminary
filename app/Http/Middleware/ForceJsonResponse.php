<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Force Accept: application/json for all API requests
        $request->headers->set('Accept', 'application/json');

        // Check for malformed JSON on POST requests
        if ($request->isMethod('POST') && $request->getContentTypeFormat() === 'json') {
            $content = $request->getContent();
            if (!empty($content) && json_decode($content) === null && json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Malformed JSON in request body.',
                ], 400);
            }
        }

        // Handle non-JSON content type on POST to analyze-ticket
        if ($request->isMethod('POST') && $request->is('analyze-ticket')) {
            $contentType = $request->header('Content-Type', '');
            if (!empty($contentType) && !str_contains($contentType, 'json')) {
                return response()->json([
                    'error' => 'Content-Type must be application/json.',
                ], 400);
            }

            // Handle empty body
            if (empty($request->getContent())) {
                return response()->json([
                    'error' => 'Request body is required.',
                ], 400);
            }
        }

        return $next($request);
    }
}

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'QueueStorm Investigator',
        'team' => 'OneDeroid',
        'contest' => 'SUST CSE Carnival 2026 — Codex Community Hackathon',
        'round' => 'Online Preliminary Qualification',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /health' => 'Service health check',
            'POST /analyze-ticket' => 'Analyze a support ticket',
        ],
    ]);
});

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeTicketRequest;
use App\Services\TicketAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AnalyzeTicketController extends Controller
{
    public function __construct(
        private TicketAnalyzerService $analyzer
    ) {}

    public function __invoke(AnalyzeTicketRequest $request): JsonResponse
    {
        // Prevent PHP from killing the process — we handle our own timeouts
        set_time_limit(60);

        try {
            // Use all input (validated fields + any extras like metadata)
            $input = $request->all();

            // Additional semantic validation
            if (!isset($input['complaint']) || trim($input['complaint']) === '') {
                return response()->json([
                    'error' => 'Complaint text cannot be empty.',
                ], 422);
            }

            $result = $this->analyzer->analyze($input);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('AnalyzeTicket error', [
                'message' => $e->getMessage(),
                'ticket_id' => $request->input('ticket_id', 'unknown'),
            ]);

            // Never return 500 — return a safe fallback 200 instead
            return response()->json([
                'ticket_id' => $request->input('ticket_id', 'unknown'),
                'relevant_transaction_id' => null,
                'evidence_verdict' => 'insufficient_data',
                'case_type' => 'other',
                'severity' => 'medium',
                'department' => 'customer_support',
                'agent_summary' => 'Ticket requires manual review due to processing issue.',
                'recommended_next_action' => 'Escalate to appropriate team for manual investigation.',
                'customer_reply' => 'Thank you for reaching out. Our team is reviewing your case and will contact you through official channels. Please do not share your PIN or OTP with anyone.',
                'human_review_required' => true,
                'confidence' => 0.3,
                'reason_codes' => ['error_fallback'],
            ], 200);
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SafetyGuardService
{
    /**
     * Patterns that indicate asking for sensitive credentials.
     */
    private const CREDENTIAL_REQUEST_PATTERNS = [
        '/\b(share|provide|send|give|enter|type|tell|confirm|verify)\b.{0,30}\b(pin|otp|password|passcode|secret\s*code|card\s*number|cvv|cvc)\b/i',
        '/\b(pin|otp|password|passcode|secret\s*code)\b.{0,30}\b(share|provide|send|give|enter|type|tell)\b/i',
        '/what\s+is\s+your\s+(pin|otp|password)/i',
        '/please\s+(share|provide|give|send|enter)\s+.{0,20}(pin|otp|password)/i',
        '/need\s+your\s+(pin|otp|password|card\s*number)/i',
        '/verify\s+(your\s+)?(identity|account)\s+.{0,30}(pin|otp|password)/i',
    ];

    /**
     * Patterns that indicate unauthorized refund/reversal promises.
     */
    private const UNAUTHORIZED_PROMISE_PATTERNS = [
        '/\b(we\s+will|we\'ll|we\s+are\s+going\s+to|we\s+shall)\s+(refund|reverse|return|credit|unblock|recover|restore)\b/i',
        '/\b(your\s+money|the\s+amount|funds)\s+(will\s+be|has\s+been|have\s+been)\s+(refund|return|credit|reverse)/i',
        '/\bguarantee.{0,20}(refund|reversal|return)/i',
        '/\bconfirm.{0,15}(refund|reversal|return|unblock)/i',
        '/\b(refund|reversal)\s+(is|has\s+been)\s+(processed|approved|confirmed|initiated)\b/i',
    ];

    /**
     * Safe replacement phrases.
     */
    private const SAFE_REFUND_PHRASE = 'any eligible amount will be returned through official channels';

    /**
     * Enforce safety rules on the output.
     */
    public function enforce(array $data): array
    {
        // Check and sanitize customer_reply
        if (isset($data['customer_reply'])) {
            $data['customer_reply'] = $this->sanitizeCustomerReply($data['customer_reply']);
        }

        // Check recommended_next_action for unauthorized promises
        if (isset($data['recommended_next_action'])) {
            $data['recommended_next_action'] = $this->sanitizeNextAction($data['recommended_next_action']);
        }

        // Ensure phishing cases are properly routed
        if (($data['case_type'] ?? '') === 'phishing_or_social_engineering') {
            $data['severity'] = 'critical';
            $data['department'] = 'fraud_risk';
            $data['human_review_required'] = true;
        }

        // Ensure credential safety reminder in customer_reply (always safe to include)
        if (isset($data['customer_reply'])) {
            $language = $data['_language'] ?? 'en';
            $data['customer_reply'] = $this->ensureSafetyReminder($data['customer_reply'], $language);
        }

        // Ensure high-value or inconsistent cases get human review
        if (($data['evidence_verdict'] ?? '') === 'inconsistent') {
            $data['human_review_required'] = true;
        }
        if (($data['severity'] ?? '') === 'critical') {
            $data['human_review_required'] = true;
        }

        return $data;
    }

    private function sanitizeCustomerReply(string $reply): string
    {
        // Check for credential requests
        foreach (self::CREDENTIAL_REQUEST_PATTERNS as $pattern) {
            if (preg_match($pattern, $reply)) {
                Log::warning('Safety: Credential request detected in customer_reply', ['pattern' => $pattern]);
                // Replace the problematic sentence
                $reply = preg_replace($pattern, 'Please do not share your PIN or OTP with anyone', $reply);
            }
        }

        // Check for unauthorized promises
        foreach (self::UNAUTHORIZED_PROMISE_PATTERNS as $pattern) {
            if (preg_match($pattern, $reply)) {
                Log::warning('Safety: Unauthorized promise detected in customer_reply', ['pattern' => $pattern]);
                $reply = preg_replace($pattern, self::SAFE_REFUND_PHRASE, $reply);
            }
        }

        return $reply;
    }

    private function sanitizeNextAction(string $action): string
    {
        // Only check for direct unauthorized promises in next action
        foreach (self::UNAUTHORIZED_PROMISE_PATTERNS as $pattern) {
            if (preg_match($pattern, $action)) {
                Log::warning('Safety: Unauthorized promise in recommended_next_action');
                $action = preg_replace($pattern, 'review eligibility for resolution through standard process', $action);
            }
        }

        return $action;
    }

    private function ensureSafetyReminder(string $reply, string $language): string
    {
        $hasPinWarning = preg_match('/(pin|otp|password|পিন|ওটিপি)/i', $reply);

        if (!$hasPinWarning) {
            if ($language === 'bn') {
                $reply .= ' অনুগ্রহ করে কারো সাথে আপনার পিন বা ওটিপি শেয়ার করবেন না।';
            } else {
                $reply .= ' Please do not share your PIN or OTP with anyone.';
            }
        }

        return $reply;
    }
}

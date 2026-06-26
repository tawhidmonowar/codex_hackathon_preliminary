<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnalyzeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'string'],
            'complaint' => ['required', 'string', 'min:1'],
            'language' => ['sometimes', 'nullable', 'string'],
            'channel' => ['sometimes', 'nullable', 'string'],
            'user_type' => ['sometimes', 'nullable', 'string'],
            'campaign_context' => ['sometimes', 'nullable', 'string'],
            'transaction_history' => ['sometimes', 'nullable', 'array'],
            'transaction_history.*.transaction_id' => ['sometimes', 'string'],
            'transaction_history.*.timestamp' => ['sometimes', 'string'],
            'transaction_history.*.type' => ['sometimes', 'string'],
            'transaction_history.*.amount' => ['sometimes', 'numeric'],
            'transaction_history.*.counterparty' => ['sometimes', 'string'],
            'transaction_history.*.status' => ['sometimes', 'string'],
            'metadata' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * Get the validated data, but also pass through all input for flexibility.
     */
    public function validatedWithExtras(): array
    {
        return $this->all();
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}

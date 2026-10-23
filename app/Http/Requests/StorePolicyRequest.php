<?php

namespace App\Http\Requests;

use App\Models\PolicyRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:128'],
            'agent_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d'],
            'destination' => ['required', 'string', Rule::in(PolicyRequest::SUPPORTED_DESTINATIONS)],
            'trip_start' => ['required', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', Rule::in(PolicyRequest::SUPPORTED_PLANS)],
            'sum_insured' => ['required', Rule::in(PolicyRequest::SUPPORTED_SUMS_INSURED)],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip_end date must be on or after the trip_start date.',
            'destination.in' => 'The destination must be either DOMESTIC or INTERNATIONAL.',
            'plan_code.in' => 'The plan_code must be one of: BASIC, PLUS, PREMIUM.',
            'sum_insured.in' => 'The sum_insured must be one of: 100000, 300000, 500000.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed for policy request',
            'errors' => $validator->errors(),
        ], 422));
    }
}

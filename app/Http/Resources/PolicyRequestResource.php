<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_request_id' => $this->client_request_id,
            'agent_id' => $this->agent_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'travel_days' => $this->travel_days,
            'plan_code' => $this->plan_code,
            'sum_insured' => (float) $this->sum_insured,
            'pricing' => [
                'base_daily_rate' => (float) $this->base_daily_rate,
                'age_at_trip_start' => $this->age_at_trip_start,
                'age_multiplier' => (float) $this->age_multiplier,
                'destination_multiplier' => (float) $this->destination_multiplier,
                'coverage_multiplier' => (float) $this->coverage_multiplier,
                'premium_amount' => (float) $this->premium_amount,
                'breakdown' => $this->pricing_breakdown,
            ],
            'status' => $this->status,
            'policy_number' => $this->policy_number,
            'provider_reference' => $this->provider_reference,
            'attempt_count' => $this->attempt_count,
            'last_error' => $this->last_error,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'audit_trail' => $this->whenLoaded('stateHistory', function () {
                return $this->stateHistory->map(fn($item) => [
                    'id' => $item->id,
                    'from_status' => $item->from_status,
                    'to_status' => $item->to_status,
                    'reason' => $item->reason,
                    'metadata' => $item->metadata,
                    'timestamp' => $item->created_at?->toIso8601String(),
                ]);
            }),
            'attempts' => $this->whenLoaded('attempts', function () {
                return $this->attempts->map(fn($attempt) => [
                    'id' => $attempt->id,
                    'attempt_number' => $attempt->attempt_number,
                    'scenario' => $attempt->scenario,
                    'http_status' => $attempt->http_status,
                    'status' => $attempt->status,
                    'error_message' => $attempt->error_message,
                    'timestamp' => $attempt->created_at?->toIso8601String(),
                ]);
            }),
        ];
    }
}

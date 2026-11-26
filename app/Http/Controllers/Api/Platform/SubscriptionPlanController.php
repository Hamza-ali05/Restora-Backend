<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => $this->payload($plan));

        return response()->json(['plans' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => $data['name'],
            'slug' => SubscriptionPlan::makeSlug($data['name']),
            'price_monthly' => $data['price_monthly'],
            'currency' => strtoupper($data['currency'] ?? 'PKR'),
            'features' => $data['features'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['plan' => $this->payload($plan)], 201);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name']) && $data['name'] !== $subscriptionPlan->name) {
            $data['slug'] = SubscriptionPlan::makeSlug($data['name']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $subscriptionPlan->update($data);

        return response()->json([
            'plan' => $this->payload($subscriptionPlan->fresh()->loadCount('subscriptions')),
        ]);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->update(['is_active' => false]);

        return response()->json([
            'message' => 'Plan deactivated.',
            'plan' => $this->payload($subscriptionPlan->fresh()->loadCount('subscriptions')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'currency' => $plan->currency,
            'features' => $plan->features ?? [],
            'is_active' => $plan->is_active,
            'subscriptions_count' => $plan->subscriptions_count ?? $plan->subscriptions()->count(),
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }
}

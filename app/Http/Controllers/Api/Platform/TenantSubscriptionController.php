<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TenantSubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $subscriptions = TenantSubscription::query()
            ->with(['tenant:id,name,slug,email,is_active', 'plan'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantSubscription $sub) => $this->payload($sub));

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'subscription_plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'status' => ['nullable', Rule::in(['active', 'past_due', 'cancelled', 'trialing'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'renews_at' => ['nullable', 'date'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
        if (! $plan->is_active) {
            return response()->json(['message' => 'Cannot assign an inactive plan.'], 422);
        }

        Tenant::query()->findOrFail($data['tenant_id']);

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();

        $subscription = TenantSubscription::query()->create([
            'tenant_id' => $data['tenant_id'],
            'subscription_plan_id' => $data['subscription_plan_id'],
            'status' => $data['status'] ?? 'active',
            'starts_at' => $startsAt,
            'ends_at' => $data['ends_at'] ?? null,
            'renews_at' => $data['renews_at'] ?? $startsAt->copy()->addMonth(),
        ]);

        return response()->json([
            'subscription' => $this->payload($subscription->load(['tenant:id,name,slug,email,is_active', 'plan'])),
        ], 201);
    }

    public function update(Request $request, TenantSubscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['sometimes', 'uuid', 'exists:subscription_plans,id'],
            'status' => ['sometimes', Rule::in(['active', 'past_due', 'cancelled', 'trialing'])],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'renews_at' => ['nullable', 'date'],
        ]);

        $subscription->update($data);

        return response()->json([
            'subscription' => $this->payload($subscription->fresh()->load(['tenant:id,name,slug,email,is_active', 'plan'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(TenantSubscription $sub): array
    {
        return [
            'id' => $sub->id,
            'tenant_id' => $sub->tenant_id,
            'subscription_plan_id' => $sub->subscription_plan_id,
            'status' => $sub->status,
            'starts_at' => $sub->starts_at,
            'ends_at' => $sub->ends_at,
            'renews_at' => $sub->renews_at,
            'tenant' => $sub->tenant,
            'plan' => $sub->plan ? [
                'id' => $sub->plan->id,
                'name' => $sub->plan->name,
                'slug' => $sub->plan->slug,
                'price_monthly' => $sub->plan->price_monthly,
                'currency' => $sub->plan->currency,
            ] : null,
            'created_at' => $sub->created_at,
            'updated_at' => $sub->updated_at,
        ];
    }
}

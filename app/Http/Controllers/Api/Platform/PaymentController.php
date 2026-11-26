<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(): JsonResponse
    {
        $payments = SubscriptionPayment::query()
            ->with(['tenant:id,name,slug', 'subscription.plan:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SubscriptionPayment $p) => $this->payload($p));

        return response()->json(['payments' => $payments]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_subscription_id' => ['required', 'uuid', 'exists:tenant_subscriptions,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['paid', 'failed', 'refunded'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription = TenantSubscription::query()->findOrFail($data['tenant_subscription_id']);

        $payment = SubscriptionPayment::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'tenant_subscription_id' => $subscription->id,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? $subscription->plan?->currency ?? 'PKR'),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'paid_at' => $data['paid_at'] ?? now(),
            'status' => $data['status'] ?? 'paid',
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'payment' => $this->payload($payment->load(['tenant:id,name,slug', 'subscription.plan:id,name'])),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(SubscriptionPayment $p): array
    {
        return [
            'id' => $p->id,
            'tenant_id' => $p->tenant_id,
            'tenant_subscription_id' => $p->tenant_subscription_id,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'period_start' => $p->period_start,
            'period_end' => $p->period_end,
            'paid_at' => $p->paid_at,
            'status' => $p->status,
            'note' => $p->note,
            'tenant' => $p->tenant?->only(['id', 'name', 'slug']),
            'plan' => $p->subscription?->plan?->only(['id', 'name']),
            'created_at' => $p->created_at,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $paymentsThisMonth = SubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd]);

        $subscriptionCounts = TenantSubscription::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tenantGrowth = $this->monthlySeries(
            Tenant::query()
                ->selectRaw($this->monthKeyExpr().' as month_key, COUNT(*) as total')
                ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
                ->groupBy('month_key')
                ->pluck('total', 'month_key')
                ->all()
        );

        $revenueTrend = $this->monthlySeries(
            SubscriptionPayment::query()
                ->selectRaw($this->monthKeyExpr('paid_at').' as month_key, COALESCE(SUM(amount),0) as total')
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
                ->groupBy('month_key')
                ->pluck('total', 'month_key')
                ->all(),
            asFloat: true
        );

        $supportBreakdown = [
            ['name' => 'New', 'value' => SupportMessage::query()->where('status', 'new')->count(), 'key' => 'new'],
            ['name' => 'Read', 'value' => SupportMessage::query()->where('status', 'read')->count(), 'key' => 'read'],
            ['name' => 'Resolved', 'value' => SupportMessage::query()->where('status', 'resolved')->count(), 'key' => 'resolved'],
        ];

        $subscriptionMix = [
            ['name' => 'Active', 'value' => (int) ($subscriptionCounts['active'] ?? 0), 'key' => 'active'],
            ['name' => 'Trialing', 'value' => (int) ($subscriptionCounts['trialing'] ?? 0), 'key' => 'trialing'],
            ['name' => 'Past due', 'value' => (int) ($subscriptionCounts['past_due'] ?? 0), 'key' => 'past_due'],
            ['name' => 'Cancelled', 'value' => (int) ($subscriptionCounts['cancelled'] ?? 0), 'key' => 'cancelled'],
        ];

        $topTenants = Tenant::query()
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->with(['currentSubscription.plan'])
            ->orderByDesc('paid_total')
            ->limit(5)
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'is_active' => $t->is_active,
                'plan' => $t->currentSubscription?->plan?->name,
                'paid_total' => (float) ($t->paid_total ?? 0),
            ]);

        return response()->json([
            'stats' => [
                'tenants_total' => Tenant::query()->count(),
                'tenants_active' => Tenant::query()->where('is_active', true)->count(),
                'tenants_inactive' => Tenant::query()->where('is_active', false)->count(),
                'subscriptions_active' => (int) ($subscriptionCounts['active'] ?? 0),
                'subscriptions_trialing' => (int) ($subscriptionCounts['trialing'] ?? 0),
                'subscriptions_past_due' => (int) ($subscriptionCounts['past_due'] ?? 0),
                'subscriptions_cancelled' => (int) ($subscriptionCounts['cancelled'] ?? 0),
                'payments_month_count' => (clone $paymentsThisMonth)->count(),
                'payments_month_sum' => (float) (clone $paymentsThisMonth)->sum('amount'),
                'support_new' => SupportMessage::query()->where('status', 'new')->count(),
                'support_open' => SupportMessage::query()->whereIn('status', ['new', 'read'])->count(),
                'support_resolved' => SupportMessage::query()->where('status', 'resolved')->count(),
            ],
            'charts' => [
                'tenant_growth' => $tenantGrowth,
                'revenue_trend' => $revenueTrend,
                'subscription_mix' => $subscriptionMix,
                'support_breakdown' => $supportBreakdown,
            ],
            'top_tenants' => $topTenants,
        ]);
    }

    protected function monthKeyExpr(string $column = 'created_at'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * @param  array<string, mixed>  $keyed
     * @return list<array{month: string, label: string, value: float|int}>
     */
    protected function monthlySeries(array $keyed, bool $asFloat = false): array
    {
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->startOfMonth();
            $key = $date->format('Y-m');
            $raw = $keyed[$key] ?? 0;
            $series[] = [
                'month' => $key,
                'label' => $date->format('M'),
                'value' => $asFloat ? (float) $raw : (int) $raw,
            ];
        }

        return $series;
    }
}

<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenantGrowth = $this->monthlySeries(
            Tenant::query()
                ->selectRaw($this->monthKeyExpr().' as month_key, COUNT(*) as total')
                ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
                ->groupBy('month_key')
                ->pluck('total', 'month_key')
                ->all()
        );

        $supportBreakdown = [
            ['name' => 'New', 'value' => SupportMessage::query()->where('status', 'new')->count(), 'key' => 'new'],
            ['name' => 'Read', 'value' => SupportMessage::query()->where('status', 'read')->count(), 'key' => 'read'],
            ['name' => 'Resolved', 'value' => SupportMessage::query()->where('status', 'resolved')->count(), 'key' => 'resolved'],
        ];

        return response()->json([
            'stats' => [
                'tenants_total' => Tenant::query()->count(),
                'tenants_active' => Tenant::query()->where('is_active', true)->count(),
                'tenants_inactive' => Tenant::query()->where('is_active', false)->count(),
                'support_new' => SupportMessage::query()->where('status', 'new')->count(),
                'support_open' => SupportMessage::query()->whereIn('status', ['new', 'read'])->count(),
                'support_resolved' => SupportMessage::query()->where('status', 'resolved')->count(),
            ],
            'charts' => [
                'tenant_growth' => $tenantGrowth,
                'support_breakdown' => $supportBreakdown,
            ],
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

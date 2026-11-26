<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id')) {
                return;
            }

            $tenant = TenantContext::tenant();

            if ($tenant) {
                $model->setAttribute('tenant_id', $tenant->id);
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenant = TenantContext::tenant();

            if ($tenant) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $tenant->id
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

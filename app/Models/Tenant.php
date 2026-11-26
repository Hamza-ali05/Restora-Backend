<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'contact_number',
        'other_contact_number',
        'email',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public static function makeSlug(string $restaurantName): string
    {
        $slug = Str::slug($restaurantName);

        if ($slug === '') {
            $slug = 'restaurant';
        }

        $base = $slug;
        $counter = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function url(?string $scheme = null): string
    {
        $scheme ??= app()->environment('local') ? 'http' : 'https';
        $base = app()->environment('local')
            ? config('tenancy.local_tenant_base_domain')
            : config('tenancy.tenant_base_domain');

        $port = '';
        if (app()->environment('local')) {
            $frontendPort = env('FRONTEND_PORT', '5173');
            $port = ':'.$frontendPort;
        }

        return "{$scheme}://{$this->slug}.{$base}{$port}";
    }
}

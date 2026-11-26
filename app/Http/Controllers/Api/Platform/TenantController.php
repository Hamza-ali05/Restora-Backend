<?php

namespace App\Http\Controllers\Api\Platform;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with(['currentSubscription.plan'])
            ->withCount('users')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tenant $tenant) => $this->tenantPayload($tenant));

        return response()->json(['tenants' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:30'],
            'other_contact_number' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:tenants,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'restaurant_name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Tenant::makeSlug($data['restaurant_name']);

        $result = DB::transaction(function () use ($data, $slug) {
            $tenant = Tenant::query()->create([
                'name' => $data['restaurant_name'],
                'slug' => $slug,
                'contact_number' => $data['contact_number'],
                'other_contact_number' => $data['other_contact_number'] ?? null,
                'email' => $data['email'],
                'is_active' => true,
            ]);

            $owner = User::query()->create([
                'tenant_id' => $tenant->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'mobile_number' => $data['contact_number'],
                'password' => $data['password'],
                'role' => UserRole::TenantOwner,
                'email_verified_at' => now(),
            ]);

            return compact('tenant', 'owner');
        });

        /** @var Tenant $tenant */
        $tenant = $result['tenant'];

        return response()->json([
            'tenant' => $this->tenantPayload($tenant->loadCount('users')->load('currentSubscription.plan')),
            'owner' => [
                'id' => $result['owner']->id,
                'first_name' => $result['owner']->first_name,
                'last_name' => $result['owner']->last_name,
                'email' => $result['owner']->email,
                'role' => $result['owner']->role->value,
            ],
            'url' => $tenant->url(),
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'tenant' => $this->tenantPayload($tenant->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:30'],
            'other_contact_number' => ['nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:tenants,email,'.$tenant->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name']) && $data['name'] !== $tenant->name) {
            $data['slug'] = Tenant::makeSlug($data['name']);
        }

        $tenant->update($data);

        return response()->json([
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => true]);

        return response()->json([
            'message' => 'Tenant activated.',
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function deactivate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => false]);

        return response()->json([
            'message' => 'Tenant deactivated.',
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        return $this->deactivate($tenant);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tenantPayload(Tenant $tenant): array
    {
        $sub = $tenant->currentSubscription;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'contact_number' => $tenant->contact_number,
            'other_contact_number' => $tenant->other_contact_number,
            'email' => $tenant->email,
            'is_active' => $tenant->is_active,
            'users_count' => $tenant->users_count ?? $tenant->users()->count(),
            'url' => $tenant->url(),
            'subscription' => $sub ? [
                'id' => $sub->id,
                'status' => $sub->status,
                'plan_name' => $sub->plan?->name,
                'plan_id' => $sub->subscription_plan_id,
                'price_monthly' => $sub->plan?->price_monthly,
                'currency' => $sub->plan?->currency,
                'renews_at' => $sub->renews_at,
            ] : null,
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
        ];
    }
}

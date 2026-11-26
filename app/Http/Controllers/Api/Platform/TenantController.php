<?php

namespace App\Http\Controllers\Api\Platform;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    /**
     * Require the signed-in platform admin password for sensitive restaurant actions.
     */
    protected function assertAdminPassword(Request $request): void
    {
        $request->validate([
            'admin_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($request->input('admin_password'), $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => ['Platform admin password is incorrect.'],
            ]);
        }
    }

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
            'restaurant_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^03\d{2}-?\d{7}$/'],
            'other_contact_number' => ['required', 'string', 'max:30', 'regex:/^03\d{2}-?\d{7}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:tenants,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'logo' => ['nullable', 'file', 'mimes:png', 'max:2048'],
        ]);

        $slug = Tenant::makeSlug($data['restaurant_name']);
        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('tenant-logos', 'public');
        }

        try {
            $result = DB::transaction(function () use ($data, $slug, $logoPath) {
                $tenant = Tenant::query()->create([
                    'name' => $data['restaurant_name'],
                    'slug' => $slug,
                    'contact_number' => $data['contact_number'],
                    'other_contact_number' => $data['other_contact_number'],
                    'email' => $data['email'],
                    'location' => $data['location'],
                    'logo' => $logoPath,
                    'is_active' => true,
                ]);

                $owner = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'first_name' => Str::limit($data['restaurant_name'], 100, ''),
                    'last_name' => 'Owner',
                    'email' => $data['email'],
                    'mobile_number' => $data['contact_number'],
                    'password' => $data['password'],
                    'role' => UserRole::TenantOwner,
                    'email_verified_at' => now(),
                ]);

                return compact('tenant', 'owner');
            });
        } catch (\Throwable $e) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $e;
        }

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
        $this->assertAdminPassword($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'restaurant_name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:30', 'regex:/^03\d{2}-?\d{7}$/'],
            'other_contact_number' => ['sometimes', 'string', 'max:30', 'regex:/^03\d{2}-?\d{7}$/'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:tenants,email,'.$tenant->id],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'admin_password' => ['required', 'string'],
        ]);

        unset($data['admin_password']);

        if (isset($data['restaurant_name'])) {
            $data['name'] = $data['restaurant_name'];
            unset($data['restaurant_name']);
        }

        if (isset($data['name']) && $data['name'] !== $tenant->name) {
            $data['slug'] = Tenant::makeSlug($data['name']);
        }

        if ($request->hasFile('logo')) {
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }

            $data['logo'] = $request->file('logo')->store('tenant-logos', 'public');
        }

        $tenant->update($data);

        return response()->json([
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function activate(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertAdminPassword($request);

        $tenant->update(['is_active' => true]);

        return response()->json([
            'message' => 'Restaurant activated.',
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function deactivate(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertAdminPassword($request);

        $tenant->update(['is_active' => false]);

        return response()->json([
            'message' => 'Restaurant deactivated.',
            'tenant' => $this->tenantPayload($tenant->fresh()->loadCount('users')->load('currentSubscription.plan')),
        ]);
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertAdminPassword($request);

        DB::transaction(function () use ($tenant) {
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }

            User::query()->where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });

        return response()->json([
            'message' => 'Restaurant deleted.',
        ]);
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
            'location' => $tenant->location,
            'logo' => $tenant->logoUrl(),
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

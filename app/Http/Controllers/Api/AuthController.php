<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $tenant = TenantContext::tenant();

        if ($tenant) {
            if ($user->role === UserRole::PlatformSuperAdmin || $user->tenant_id !== $tenant->id) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['These credentials do not belong to this restaurant.'],
                ]);
            }

            if (! $tenant->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['This restaurant account is inactive.'],
                ]);
            }
        } else {
            if ($user->role !== UserRole::PlatformSuperAdmin) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Only platform administrators can sign in here.'],
                ]);
            }
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json([
            'user' => $user ? $this->userPayload($user) : null,
            'tenant' => TenantContext::tenant()?->only(['id', 'name', 'slug', 'email', 'is_active']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->fullName(),
            'email' => $user->email,
            'mobile_number' => $user->mobile_number,
            'profile_picture' => $user->profile_picture
                ? url('storage/'.$user->profile_picture)
                : null,
            'role' => $user->role->value,
        ];
    }
}

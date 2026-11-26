<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'mobile_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'profile_picture' => ['sometimes', 'file', 'mimes:png', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $data['profile_picture'] = $request->file('profile_picture')
                ->store('profile-pictures', 'public');
        }

        $user->update($data);

        return response()->json([
            'user' => [
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
            ],
        ]);
    }
}

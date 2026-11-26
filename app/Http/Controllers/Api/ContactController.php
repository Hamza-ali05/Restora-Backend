<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = SupportMessage::query()->create([
            ...$data,
            'status' => 'new',
            'tenant_id' => TenantContext::tenant()?->id,
        ]);

        return response()->json([
            'message' => 'Thank you. Your message has been received.',
            'id' => $message->id,
        ], 201);
    }
}

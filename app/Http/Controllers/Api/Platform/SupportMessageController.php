<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = SupportMessage::query()->orderByDesc('created_at');

        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $messages = $query->get()->map(fn (SupportMessage $m) => $this->payload($m));

        return response()->json(['messages' => $messages]);
    }

    public function show(SupportMessage $supportMessage): JsonResponse
    {
        if ($supportMessage->status === 'new') {
            $supportMessage->update(['status' => 'read']);
        }

        return response()->json([
            'message' => $this->payload($supportMessage->fresh()),
        ]);
    }

    public function update(Request $request, SupportMessage $supportMessage): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'resolved'])],
        ]);

        $supportMessage->update($data);

        return response()->json([
            'message' => $this->payload($supportMessage->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(SupportMessage $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'message' => $m->message,
            'status' => $m->status,
            'tenant_id' => $m->tenant_id,
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ];
    }
}

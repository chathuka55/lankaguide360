<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    /**
     * POST /api/chat (throttled 20 per 10 minutes per IP).
     */
    public function send(ChatRequest $request, ChatbotService $chatbot): JsonResponse
    {
        return response()->json($chatbot->reply(
            $request->validated('message'),
            $request->validated('history') ?? [],
            $request->session()->getId(),
            $request->user()?->id,
        ));
    }
}

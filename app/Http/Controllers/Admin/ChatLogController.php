<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read chatbot conversations to improve FAQs and answers (SRS 6.5).
 */
class ChatLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ChatLog::class);

        $conversations = ChatLog::query()
            ->select('session_id', DB::raw('MIN(created_at) as started_at'), DB::raw('COUNT(*) as messages'), DB::raw('MAX(id) as last_id'))
            ->when($request->query('q'), fn ($q, $s) => $q->whereIn('session_id', ChatLog::where('message', 'like', "%{$s}%")->select('session_id')))
            ->groupBy('session_id')
            ->orderByDesc('last_id')
            ->paginate(25)
            ->withQueryString();

        $firstQuestions = ChatLog::whereIn('session_id', $conversations->pluck('session_id'))
            ->where('role', 'user')->orderBy('id')->get()->unique('session_id')->pluck('message', 'session_id');

        $selected = $request->query('session');

        return view('admin.chat-logs.index', [
            'conversations' => $conversations,
            'firstQuestions' => $firstQuestions,
            'selected' => $selected,
            'thread' => $selected ? ChatLog::where('session_id', $selected)->orderBy('id')->get() : collect(),
        ]);
    }
}

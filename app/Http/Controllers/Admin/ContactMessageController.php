<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contact form messages (the public form arrives in phase 5).
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        $messages = ContactMessage::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%")))
            ->when($request->query('status') === 'unread', fn ($q) => $q->where('is_read', false))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.messages.index', ['messages' => $messages]);
    }

    public function show(ContactMessage $message): View
    {
        $this->authorize('view', $message);

        if (! $message->is_read) {
            $message->forceFill(['is_read' => true])->save();
        }

        return view('admin.messages.show', ['message' => $message]);
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $message->delete();

        return redirect()->route('admin.messages.index')->with('status', 'Message deleted.');
    }
}

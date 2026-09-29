<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact', ['faqs' => Faq::active()->limit(12)->get()]);
    }

    /**
     * FR-29: store the message and email the admin. Bots that fill the hidden field get the
     * normal thank-you page but nothing is stored.
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        if (! $request->isSpam()) {
            $message = ContactMessage::create($request->safe()->except('website'));
            Mail::to(config('lankaguide.contact.admin_email'))->queue(new ContactMessageReceived($message));
        }

        return redirect()->route('contact')->with('status', 'Thank you! Your message has been sent. We usually reply within one working day.');
    }
}

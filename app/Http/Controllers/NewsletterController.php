<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class NewsletterController extends Controller
{
    public function store(NewsletterRequest $request): RedirectResponse|JsonResponse
    {
        NewsletterSubscriber::firstOrCreate(['email' => strtolower($request->validated('email'))]);

        $message = 'Thanks for subscribing! Travel tips arrive about once a month.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('newsletter', $message);
    }
}

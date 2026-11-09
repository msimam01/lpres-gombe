<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate(['email' => $validated['email']]);

        return redirect()
            ->back()
            ->with('newsletter-status', $subscriber->wasRecentlyCreated
                ? 'Subscribed. You will be notified of new updates.'
                : 'You are already subscribed.');
    }
}

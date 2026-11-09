<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterSubscriberAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/subscribers', [
            'subscribers' => NewsletterSubscriber::query()
                ->orderByDesc('id')
                ->paginate(50)
                ->through(fn (NewsletterSubscriber $s) => [
                    'id' => $s->id,
                    'email' => $s->email,
                    'created_at' => $s->created_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        NewsletterSubscriber::query()->findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Subscriber removed.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactEnquiry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Triage for the public contact form: list submissions, mark handled /
 * reopen, delete once processed. Read-only content — no editing.
 */
class ContactEnquiryAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/enquiries', [
            'enquiries' => ContactEnquiry::query()
                ->orderByRaw('handled_at IS NULL DESC')
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (ContactEnquiry $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'email' => $e->email,
                    'phone' => $e->phone,
                    'message' => $e->message,
                    'handled_at' => $e->handled_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $enquiry = ContactEnquiry::query()->findOrFail($id);
        $enquiry->update(['handled_at' => $enquiry->handled_at ? null : now()]);

        return redirect()->back()->with('success', 'Enquiry updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        ContactEnquiry::query()->findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Enquiry deleted.');
    }
}

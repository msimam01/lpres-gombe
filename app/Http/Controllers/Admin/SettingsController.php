<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site-wide settings: the contact block shared by the footer, contact page
 * and social links, plus the public logo.
 */
class SettingsController extends Controller
{
    private const KEYS = [
        'contact_address', 'contact_email', 'contact_phone',
        'social_facebook', 'social_x', 'social_linkedin', 'social_youtube',
    ];

    public function edit(): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => collect(self::KEYS)
                ->flip()
                ->map(fn ($unused, string $key) => Setting::get($key, ''))
                ->merge(['logo_path' => Setting::get('logo_path')]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_x' => ['nullable', 'url', 'max:255'],
            'social_linkedin' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'logo_path' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        foreach (self::KEYS as $key) {
            Setting::put($key, $validated[$key] ?? null);
        }

        if ($request->hasFile('logo_path')) {
            $old = Setting::get('logo_path');

            if ($old && str_starts_with($old, 'uploads/')) {
                Storage::disk('public')->delete($old);
            }

            $path = $request->file('logo_path')->store('uploads/settings', 'public');
            Setting::put('logo_path', $path);
        }

        // Clear the cached shared settings so the change is public immediately.
        cache()->forget('site.contact-settings');

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}

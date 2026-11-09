<?php

namespace App\Settings;

use App\Models\Setting;

/**
 * Shared, cache-backed access to the site contact settings (address, email,
 * phone, social links and logo) used by the public footer, contact page and
 * header logo.
 */
class ContactSettings
{
    public const KEYS = [
        'contact_address', 'contact_email', 'contact_phone',
        'social_facebook', 'social_x', 'social_linkedin', 'social_youtube',
        'logo_path',
    ];

    /** @var array<string, string|null> */
    public function __construct(private array $values) {}

    public static function resolve(): self
    {
        $values = cache()->remember(
            'site.contact-settings',
            now()->addMinutes(30),
            fn () => Setting::query()
                ->whereIn('key', self::KEYS)
                ->pluck('value', 'key')
                ->all(),
        );

        return new self($values);
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->values[$key] ?? $default);
    }

    public function has(string $key): bool
    {
        return filled($this->values[$key] ?? null);
    }

    /** Resolved, frontend-friendly values with sensible logo fallback. */
    public function shared(): array
    {
        return [
            'address' => $this->get('contact_address'),
            'email' => $this->get('contact_email'),
            'phone' => $this->get('contact_phone'),
            'socials' => collect([
                'facebook' => $this->get('social_facebook'),
                'x' => $this->get('social_x'),
                'linkedin' => $this->get('social_linkedin'),
                'youtube' => $this->get('social_youtube'),
            ])->filter()->all(),
            'logo' => $this->has('logo_path')
                ? asset('storage/'.$this->get('logo_path'))
                : asset('images/lpres/lpres-logo.jpeg'),
        ];
    }
}

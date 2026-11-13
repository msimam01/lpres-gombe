<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * External document links must be https. Google addresses are restricted to
 * the Drive/Docs hosts so a general google.com link is never presented as a
 * document, while any other https host is allowed for published files.
 */
class DocumentUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $parts = parse_url($value);

        if ($parts === false || empty($parts['host']) || strtolower($parts['scheme'] ?? '') !== 'https') {
            $fail('The :attribute field must be a valid https address.');

            return;
        }

        $host = strtolower($parts['host']);

        if (str_contains($host, 'google.') && ! in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            $fail('Google Drive links must use drive.google.com or docs.google.com.');
        }
    }
}

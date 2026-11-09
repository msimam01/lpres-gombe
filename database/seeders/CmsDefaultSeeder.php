<?php

namespace Database\Seeders;

use App\Models\ProjectComponent;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Initial CMS baseline: the shared contact settings and the three L-PRES
 * Project Components already established on the completed homepage. These are
 * organisation facts (not scraped content); everything else is added through
 * the admin panel afterwards.
 */
class CmsDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'contact_address' => 'GSADP Quarters, Opposite Buhari Estate, Gombe, Gombe State Nigeria',
            'contact_email' => 'gombe@lpresnigeria.gov.ng',
            'contact_phone' => '+234 8035336243',
            'social_youtube' => 'https://www.youtube.com/@gombelpres',
            'logo_path' => null,
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        // The three project components from the completed homepage (preserved
        // verbatim); only seeded when the table is empty.
        $existing = ProjectComponent::query()->count();

        if ($existing === 0) {
            $components = [
                ['Institutional and Innovation System Strengthening', 1],
                ['Livestock Value Chain Enhancement', 2],
                ['Crisis Prevention and Management, Conflict Mitigation, Peace-Building, Environment and Coordination', 3],
            ];

            foreach ($components as [$title, $order]) {
                ProjectComponent::create([
                    'title' => $title,
                    'display_order' => $order,
                    'status' => true,
                ]);
            }
        }

        // Reset the cached settings mirror.
        cache()->forget('site.contact-settings');
    }
}

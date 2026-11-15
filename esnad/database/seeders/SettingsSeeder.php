<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Starting values. Seeding never overwrites a value an admin has changed. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Any server outside Egypt is a cross-border transfer (PDPL).
            'data_stored_abroad' => true,
            // Business values used from phase 2 on. Stored here so they are editable from day one.
            'bar_subscription_due' => '03-31',
            'bar_unpaid_hide_after' => '06-30',
            'verification_target_hours' => 48,
            'booking_cancellation_cutoff_hours' => 24,
            'rights_request_working_days' => 6,
            'breach_pdpc_hours' => 72,
            'max_fee_percentage' => 20,
        ];
        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'version' => 1]);
        }

        $flags = [
            'search' => 'Search, lawyer profiles and booking (phase 3)',
            'request' => 'Request a lawyer now (phase 5)',
            'forum' => 'Ask a lawyer forum (phase 7)',
            'tools' => 'Free calculators (phase 7)',
            'analytics' => 'Analytics after cookie consent',
        ];
        foreach ($flags as $key => $description) {
            FeatureFlag::firstOrCreate(['key' => $key], ['enabled' => false, 'description' => $description]);
        }
    }
}

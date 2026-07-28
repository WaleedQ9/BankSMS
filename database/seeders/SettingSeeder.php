<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'telegram_chat_id' => '',
            'telegram_bot_token' => '',
            'api_key' => bin2hex(random_bytes(16)),
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(['key' => $key], [
                'key' => $key,
                'value' => $value,
                'updated_at' => now(),
            ]);
        }
    }
}

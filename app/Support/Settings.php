<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class Settings
{
    protected static ?array $cache = null;

    public const ENCRYPTED_KEYS = ['ai_api_key'];

    public const DEFAULTS = [
        'app_name' => 'SuppliRank',
        'app_tagline' => 'Rekomendasi Supplier dengan MCDM & Explainable AI',
        'company_name' => 'PT Nusantara Procurement',
        'brand_color' => '#4f46e5',
        'logo' => '',
        'default_method' => 'saw',
        'report_footer' => 'Dokumen ini dihasilkan otomatis oleh sistem rekomendasi supplier.',
        'ai_enabled' => '0',
        'ai_provider' => 'openai',
        'ai_base_url' => 'https://api.openai.com/v1',
        'ai_model' => 'gpt-4o-mini',
        'ai_api_key' => '',
    ];

    public static function all(): array
    {
        if (static::$cache === null) {
            $stored = [];
            try {
                $stored = Setting::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                $stored = [];
            }

            static::$cache = [];
            foreach (self::DEFAULTS as $key => $default) {
                $value = $stored[$key] ?? null;
                if ($value !== null && in_array($key, self::ENCRYPTED_KEYS, true)) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable) {
                        $value = '';
                    }
                }
                static::$cache[$key] = $value ?? $default;
            }
        }

        return static::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            $value = blank($value) ? null : Crypt::encryptString((string) $value);
        }

        Setting::query()->updateOrCreate(['key' => $key], [
            'value' => $value === null ? null : (string) $value,
            'group' => 'general',
        ]);

        static::$cache = null;
    }

    public static function flush(): void
    {
        static::$cache = null;
    }
}

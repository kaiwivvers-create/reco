<?php

use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('fmt_num')) {
    function fmt_num(mixed $value, int $decimals = 2): string
    {
        $value = (float) $value;
        if ($decimals === 2 && abs($value - round($value)) < 0.000001) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, $decimals, ',', '.');
    }
}

if (! function_exists('fmt_money')) {
    function fmt_money(mixed $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date($date, bool $withTime = true): string
    {
        if (! $date) {
            return '-';
        }

        $carbon = $date instanceof \DateTimeInterface ? \Carbon\Carbon::instance($date) : \Carbon\Carbon::parse($date);

        return $withTime
            ? $carbon->locale('id')->translatedFormat('d M Y, H:i')
            : $carbon->locale('id')->translatedFormat('d M Y');
    }
}

if (! function_exists('can_view')) {
    function can_view(string $module): bool
    {
        return Permissions::current($module, 'view');
    }
}

if (! function_exists('can_manage')) {
    function can_manage(string $module): bool
    {
        return Permissions::current($module, 'manage');
    }
}

if (! function_exists('brand_color')) {
    function brand_color(): string
    {
        $color = (string) setting('brand_color', '#4f46e5');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#4f46e5';
    }
}

if (! function_exists('app_logo_url')) {
    function app_logo_url(): ?string
    {
        $logo = (string) setting('logo', '');

        return $logo !== '' && Storage::disk('public')->exists($logo)
            ? Storage::disk('public')->url($logo)
            : null;
    }
}

if (! function_exists('app_initials')) {
    function app_initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials ?: 'S';
    }
}

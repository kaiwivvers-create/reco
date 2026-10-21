<?php

namespace App\Http\Controllers;

use App\Services\AiNarrativeService;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Settings::all();
        $hasApiKey = filled($settings['ai_api_key'] ?? null);

        return view('settings.index', compact('settings', 'hasApiKey'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_tagline' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_method' => ['required', 'in:saw,topsis'],
            'report_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'ai_enabled' => ['nullable', 'boolean'],
            'ai_provider' => ['nullable', 'string', 'max:30'],
            'ai_base_url' => ['nullable', 'url', 'max:200'],
            'ai_model' => ['nullable', 'string', 'max:80'],
            'ai_api_key' => ['nullable', 'string', 'max:300'],
            'clear_ai_key' => ['nullable', 'boolean'],
        ], [
            'brand_color.regex' => 'Warna brand harus berupa kode hex, contoh #4f46e5.',
            'logo.image' => 'Logo harus berupa gambar PNG, JPG, atau WEBP.',
        ]);

        $old = Settings::all();
        $oldLogo = $old['logo'] ?? '';

        foreach (['app_name', 'app_tagline', 'company_name', 'brand_color', 'default_method', 'report_footer', 'ai_provider', 'ai_base_url', 'ai_model'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }

        Settings::set('ai_enabled', $request->boolean('ai_enabled') ? '1' : '0');

        if ($request->hasFile('logo')) {
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            $path = $request->file('logo')->store('logos', 'public');
            Settings::set('logo', $path);
        } elseif ($request->boolean('remove_logo') && $oldLogo) {
            if (Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            Settings::set('logo', '');
        }

        if ($request->boolean('clear_ai_key')) {
            Settings::set('ai_api_key', '');
        } elseif (filled($data['ai_api_key'] ?? null)) {
            Settings::set('ai_api_key', $data['ai_api_key']);
        }

        Settings::flush();

        $new = Settings::all();
        $mask = function (array $values) {
            if (filled($values['ai_api_key'] ?? null)) {
                $values['ai_api_key'] = '***disembunyikan***';
            }

            return $values;
        };

        ActivityLogger::log(
            'updated',
            'setting',
            null,
            'Pengaturan Aplikasi',
            $mask($old),
            $mask($new),
            'Pengaturan aplikasi diperbarui'
        );

        $message = 'Pengaturan berhasil disimpan.';
        if ($request->boolean('ai_enabled') && blank(Settings::get('ai_api_key'))) {
            return redirect()->route('settings.index')->with('success', $message)
                ->with('warning', 'Fitur AI diaktifkan tetapi API key masih kosong. Narasi AI belum dapat digunakan.');
        }

        return redirect()->route('settings.index')->with('success', $message);
    }

    public function testAi(AiNarrativeService $ai)
    {
        try {
            $reply = $ai->test();

            return response()->json(['message' => $reply]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

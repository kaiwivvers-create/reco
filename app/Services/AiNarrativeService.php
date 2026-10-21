<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Optional LLM layer. The deterministic XAI engine always works without it;
 * when an API key is configured the explanation can additionally be rewritten
 * as an executive narrative.
 */
class AiNarrativeService
{
    public function configured(): bool
    {
        return (string) Settings::get('ai_enabled') === '1' && filled(Settings::get('ai_api_key'));
    }

    public function generate(string $systemPrompt, string $userPrompt, int $maxTokens = 500): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Fitur AI belum diaktifkan. Aktifkan di Pengaturan Aplikasi dan isi API key.');
        }

        $baseUrl = rtrim((string) Settings::get('ai_base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) Settings::get('ai_model', 'gpt-4o-mini');

        $response = Http::withToken((string) Settings::get('ai_api_key'))
            ->acceptJson()
            ->timeout(35)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Permintaan ke penyedia AI gagal (HTTP '.$response->status().'). Periksa API key, base URL, dan model.');
        }

        $text = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Penyedia AI mengembalikan respons kosong.');
        }

        return trim($text);
    }

    public function test(): string
    {
        return $this->generate(
            'Anda adalah asisten pengujian koneksi. Jawab sangat singkat.',
            'Balas dengan satu kalimat: "Koneksi AI berhasil."',
            60
        );
    }
}

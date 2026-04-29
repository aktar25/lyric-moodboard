<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // KITA PAKAI HTTP UNTUK MENEMBAK API

class MoodController extends Controller
{
    /**
     * Peta emosi dominan → HSL color
     */
    private array $moodToHsl = [
        'joy'       => ['h' => 45,  's' => 85, 'l' => 55, 'mood' => 'Euphoric',   'label' => 'golden & warm'],
        'sadness'   => ['h' => 220, 's' => 60, 'l' => 45, 'mood' => 'Melancholy', 'label' => 'deep & blue'],
        'anger'     => ['h' => 5,   's' => 80, 'l' => 45, 'mood' => 'Defiant',    'label' => 'fierce & red'],
        'fear'      => ['h' => 275, 's' => 50, 'l' => 40, 'mood' => 'Haunted',    'label' => 'dim & violet'],
        'love'      => ['h' => 330, 's' => 70, 'l' => 50, 'mood' => 'Tender',     'label' => 'warm & rose'],
        'hope'      => ['h' => 160, 's' => 65, 'l' => 50, 'mood' => 'Hopeful',    'label' => 'teal & serene'],
        'nostalgia' => ['h' => 30,  's' => 40, 'l' => 60, 'mood' => 'Nostalgic',  'label' => 'sepia & fading'],
    ];

    public function index()
    {
        return view('moodboard');
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'lyrics' => 'required|string|min:10|max:2000',
        ]);

        $rawLyrics = $request->input('lyrics');
        $wordCount = count(preg_split('/\W+/', $rawLyrics, -1, PREG_SPLIT_NO_EMPTY));

        $prompt = "Sebagai penganalisis emosi lagu, berikan skor emosi (0-100) dari lirik ini: \"{$rawLyrics}\".
        Kamu HANYA boleh membalas dengan format JSON murni persis seperti ini:
        {\"joy\": 0, \"sadness\": 0, \"anger\": 0, \"fear\": 0, \"love\": 0, \"hope\": 0, \"nostalgia\": 0}";

        // Kunci API yang sudah terbukti jalan!
        $apiKey = "AIzaSyDX66GO9plBgMjcOdjllWQa9--XdPZn5Ss";

        try {
            // URL TELAH DIPERBAIKI (Ditambah -latest)
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Gagal dari Google: ' . $response->body()], 500);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Laravel Crash: ' . $e->getMessage()], 500);
        }

        $aiResult = $response->json();
        $responseText = $aiResult['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        $cleanJson = str_replace(['```json', '```'], '', $responseText);
        $scores = json_decode(trim($cleanJson), true);

        if (!$scores) {
            return response()->json(['error' => 'Jawaban AI bukan JSON: ' . $responseText], 500);
        }

        $total = array_sum($scores);

        if ($total === 0) {
             $scores = ['nostalgia' => 30, 'joy' => 10, 'sadness' => 10, 'anger' => 10, 'fear' => 10, 'love' => 10, 'hope' => 10];
             $total = array_sum($scores);
        }

        $normalized = [];
        $divisor = max($total / 100, 1);
        foreach ($scores as $emotion => $raw) {
            $normalized[$emotion] = min(100, round($raw / $divisor));
        }

        arsort($normalized);
        $dominant = array_key_first($normalized);
        $moodData = $this->moodToHsl[$dominant];

        $secondary = array_keys($normalized)[1];
        $secondaryHsl = $this->moodToHsl[$secondary];
        $w1 = $normalized[$dominant] ?? 1;
        $w2 = $normalized[$secondary] ?? 1;
        $wTotal = max($w1 + $w2, 1);

        $blendedH = round(($moodData['h'] * $w1 + $secondaryHsl['h'] * $w2) / $wTotal);
        $blendedS = round(($moodData['s'] * $w1 + $secondaryHsl['s'] * $w2) / $wTotal);
        $blendedL = round(($moodData['l'] * $w1 + $secondaryHsl['l'] * $w2) / $wTotal);

        $blendedH = ($blendedH + 360) % 360;
        $blendedS = max(20, min(90, $blendedS));
        $blendedL = max(25, min(65, $blendedL));

        $hexColor = $this->hslToHex($blendedH, $blendedS, $blendedL);
        $palette = $this->generatePalette($blendedH, $blendedS, $blendedL);

        $intensityScore = min(100, round($total / 2.5));
        $intensityLabel = match(true) {
            $intensityScore >= 80 => 'Shattering',
            $intensityScore >= 60 => 'Electric',
            $intensityScore >= 40 => 'Trembling',
            $intensityScore >= 20 => 'Smoldering',
            default               => 'Whisper',
        };

        return response()->json([
            'dominant_mood'   => $moodData['mood'],
            'tagline'         => $moodData['label'],
            'hsl'             => ['h' => $blendedH, 's' => $blendedS, 'l' => $blendedL],
            'hex'             => $hexColor,
            'palette'         => $palette,
            'emotions'        => $normalized,
            'keywords'        => ['AI Evaluated', 'Contextual', 'Deep Meaning'],
            'intensity_score' => $intensityScore,
            'intensity_label' => $intensityLabel,
            'matched_words'   => $wordCount,
            'word_count'      => $wordCount,
        ]);
    }

    private function hslToHex(int $h, int $s, int $l): string {
        $s /= 100; $l /= 100; $a = $s * min($l, 1 - $l);
        $f = function (int $n) use ($h, $l, $a): string {
            $k = ($n + $h / 30) % 12;
            $color = $l - $a * max(min($k - 3, 9 - $k, 1), -1);
            return str_pad(dechex((int) round($color * 255)), 2, '0', STR_PAD_LEFT);
        };
        return '#' . $f(0) . $f(8) . $f(4);
    }

    private function generatePalette(int $h, int $s, int $l): array {
        $stops = [
            ['h' => ($h + 30) % 360, 's' => min($s + 10, 90), 'l' => min($l + 15, 80)],
            ['h' => $h,               's' => $s,               'l' => $l],
            ['h' => ($h + 180) % 360, 's' => max($s - 10, 20), 'l' => min($l + 20, 80)],
            ['h' => ($h + 60)  % 360, 's' => $s,               'l' => max($l - 15, 15)],
            ['h' => ($h + 300) % 360, 's' => max($s - 20, 20), 'l' => min($l + 25, 80)],
        ];
        return array_map(function ($stop) {
            return array_merge($stop, ['hex' => $this->hslToHex($stop['h'], $stop['s'], $stop['l'])]);
        }, $stops);
    }
}

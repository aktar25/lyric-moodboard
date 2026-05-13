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
        $apiKey = env('GEMINI_API_KEY');

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

        $scores = $this->applyShortLyricBoost($rawLyrics, $scores);
        $total = array_sum($scores);


        $normalized = [];
        $divisor = max($total / 100, 1);
        foreach ($scores as $emotion => $raw) {
            $normalized[$emotion] = min(100, round($raw / $divisor));
        }

        arsort($normalized);
        $dominant = array_key_first($normalized);
        $moodData = $this->moodToHsl[$dominant];

        $sortedKeys = array_keys($normalized);
        $secondary = $sortedKeys[1] ?? $dominant;
        $secondaryHsl = $this->moodToHsl[$secondary] ?? $moodData;
        $w1 = $normalized[$dominant] ?? 1;
        $w2 = $normalized[$secondary] ?? 1;
        $wTotal = max($w1 + $w2, 1);

        $blendedH = $this->blendHueCircular(
            $moodData['h'],
            $secondaryHsl['h'],
            $w1,
            $w2
        );

        $blendedS = round(($moodData['s'] * $w1 + $secondaryHsl['s'] * $w2) / $wTotal);
        $blendedL = round(($moodData['l'] * $w1 + $secondaryHsl['l'] * $w2) / $wTotal);

        if ($dominant === 'love') {
            $blendedS = max($blendedS, 68);
            $blendedL = max($blendedL, 48);
        }

        $blendedH = ($blendedH + 360) % 360;
        $blendedS = max(20, min(90, $blendedS));
        $blendedL = max(25, min(70, $blendedL));


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

        private function applyShortLyricBoost(string $lyrics, array $scores): array
    {
        $wordCount = count(preg_split('/\W+/u', $lyrics, -1, PREG_SPLIT_NO_EMPTY));

        if ($wordCount > 10) {
            return $scores;
        }

        $text = mb_strtolower($lyrics, 'UTF-8');

        $loveWords = ['cinta', 'love', 'sayang', 'hati', 'rindu', 'kekasih', 'romantis'];
        $joyWords = ['bahagia', 'senang', 'indah', 'tersenyum', 'gembira'];
        $sadWords = ['sedih', 'patah', 'kecewa', 'menangis', 'hilang'];
        $hopeWords = ['harap', 'berharap', 'menanti', 'impian'];

        foreach ($loveWords as $word) {
            if (str_contains($text, $word)) {
                $scores['love'] = ($scores['love'] ?? 0) + 30;
            }
        }

        foreach ($joyWords as $word) {
            if (str_contains($text, $word)) {
                $scores['joy'] = ($scores['joy'] ?? 0) + 18;
            }
        }

        foreach ($sadWords as $word) {
            if (str_contains($text, $word)) {
                $scores['sadness'] = ($scores['sadness'] ?? 0) + 18;
            }
        }

        foreach ($hopeWords as $word) {
            if (str_contains($text, $word)) {
                $scores['hope'] = ($scores['hope'] ?? 0) + 12;
            }
        }

        foreach ($scores as $emotion => $value) {
            $scores[$emotion] = max(0, min(100, (int) round($value)));
        }

        return $scores;
    }

    private function blendHueCircular(int $h1, int $h2, int $w1, int $w2): int
    {
        $w1 = max($w1, 1);
        $w2 = max($w2, 1);

        $a1 = deg2rad($h1);
        $a2 = deg2rad($h2);

        $x = cos($a1) * $w1 + cos($a2) * $w2;
        $y = sin($a1) * $w1 + sin($a2) * $w2;

        if (abs($x) < 0.0001 && abs($y) < 0.0001) {
            return $h1;
        }

        $angle = rad2deg(atan2($y, $x));

        if ($angle < 0) {
            $angle += 360;
        }

        return (int) round($angle);
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sastrawi\Stemmer\StemmerFactory;

class MoodController extends Controller
{
    /**
     * Emotion lexicon — kata dengan bobot emosi (Inggris & Indonesia)
     */
    private array $lexicon = [
        // Joy / Happy
        'happy'      => ['joy'=>9,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>3],
        'senang'     => ['joy'=>9,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>1],
        'tawa'    => ['joy'=>8,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>1],
        'tari'     => ['joy'=>8,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>2],
        'bebas'      => ['joy'=>8,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>1],
        'cahaya'     => ['joy'=>7,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>2],
        'pagi'       => ['joy'=>6,'sadness'=>1,'anger'=>0,'fear'=>0,'love'=>2],

        // Sadness / Melancholy
        'cry'        => ['joy'=>0,'sadness'=>9,'anger'=>1,'fear'=>1,'love'=>1],
        'sedih'      => ['joy'=>0,'sadness'=>9,'anger'=>1,'fear'=>1,'love'=>0],
        'tangis'   => ['joy'=>0,'sadness'=>9,'anger'=>1,'fear'=>1,'love'=>0],
        'hancur'     => ['joy'=>0,'sadness'=>9,'anger'=>2,'fear'=>1,'love'=>1],
        'hilang'     => ['joy'=>0,'sadness'=>8,'anger'=>1,'fear'=>3,'love'=>1],
        'sendiri'    => ['joy'=>0,'sadness'=>8,'anger'=>0,'fear'=>3,'love'=>1],
        'sepi'       => ['joy'=>0,'sadness'=>9,'anger'=>0,'fear'=>2,'love'=>0],
        'rindu'      => ['joy'=>0,'sadness'=>7,'anger'=>0,'fear'=>0,'love'=>5],
        'hujan'      => ['joy'=>1,'sadness'=>6,'anger'=>0,'fear'=>1,'love'=>2],

        // Anger / Rage
        'hate'       => ['joy'=>0,'sadness'=>2,'anger'=>10,'fear'=>1,'love'=>0],
        'benci'      => ['joy'=>0,'sadness'=>2,'anger'=>10,'fear'=>1,'love'=>0],
        'marah'      => ['joy'=>0,'sadness'=>1,'anger'=>10,'fear'=>2,'love'=>0],
        'api'        => ['joy'=>1,'sadness'=>0,'anger'=>8,'fear'=>3,'love'=>1],
        'teriak'     => ['joy'=>0,'sadness'=>2,'anger'=>8,'fear'=>3,'love'=>0],
        'lawan'      => ['joy'=>0,'sadness'=>0,'anger'=>9,'fear'=>2,'love'=>0],
        'darah'      => ['joy'=>0,'sadness'=>2,'anger'=>7,'fear'=>5,'love'=>0],

        // Fear / Anxiety
        'afraid'     => ['joy'=>0,'sadness'=>2,'anger'=>1,'fear'=>9,'love'=>0],
        'takut'      => ['joy'=>0,'sadness'=>2,'anger'=>1,'fear'=>9,'love'=>0],
        'gelap'      => ['joy'=>0,'sadness'=>5,'anger'=>1,'fear'=>7,'love'=>0],
        'lari'       => ['joy'=>1,'sadness'=>1,'anger'=>2,'fear'=>6,'love'=>0],
        'sembunyi'   => ['joy'=>0,'sadness'=>3,'anger'=>1,'fear'=>7,'love'=>0],
        'dingin'     => ['joy'=>0,'sadness'=>4,'anger'=>1,'fear'=>5,'love'=>0],

        // Love / Passion
        'love'       => ['joy'=>5,'sadness'=>1,'anger'=>0,'fear'=>0,'love'=>10],
        'cinta'      => ['joy'=>5,'sadness'=>1,'anger'=>0,'fear'=>0,'love'=>10],
        'sayang'     => ['joy'=>5,'sadness'=>1,'anger'=>0,'fear'=>0,'love'=>9],
        'hati'       => ['joy'=>4,'sadness'=>3,'anger'=>0,'fear'=>0,'love'=>8],
        'bersama'    => ['joy'=>6,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>7],
        'peluk'      => ['joy'=>4,'sadness'=>1,'anger'=>0,'fear'=>0,'love'=>8],
        'indah'      => ['joy'=>7,'sadness'=>0,'anger'=>0,'fear'=>0,'love'=>6],
    ];

    /**
     * Peta emosi dominan → HSL color
     */
    private array $moodToHsl = [
        'joy'     => ['h' => 45,  's' => 85, 'l' => 55, 'mood' => 'Euphoric',   'label' => 'golden & warm'],
        'sadness' => ['h' => 220, 's' => 60, 'l' => 45, 'mood' => 'Melancholy', 'label' => 'deep & blue'],
        'anger'   => ['h' => 5,   's' => 80, 'l' => 45, 'mood' => 'Defiant',    'label' => 'fierce & red'],
        'fear'    => ['h' => 275, 's' => 50, 'l' => 40, 'mood' => 'Haunted',    'label' => 'dim & violet'],
        'love'    => ['h' => 330, 's' => 70, 'l' => 50, 'mood' => 'Tender',     'label' => 'warm & rose'],
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

        $rawLyrics = strtolower($request->input('lyrics'));

        // --- 1. PROSES STEMMING DENGAN SASTRAWI ---
        $stemmerFactory = new StemmerFactory();
        $stemmer  = $stemmerFactory->createStemmer();
        $cleanLyrics = $stemmer->stem($rawLyrics);

            // --- 2. TOKENIZING (Memecah teks yang sudah bersih) ---
        $words     = preg_split('/\W+/', $cleanLyrics, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        // --- Hitung skor emosi menggunakan lexicon ---
        $scores = ['joy' => 0, 'sadness' => 0, 'anger' => 0, 'fear' => 0, 'love' => 0];
        $matchedWords = [];

        foreach ($words as $word) {
            if (isset($this->lexicon[$word])) {
                foreach ($scores as $emotion => $val) {
                    $scores[$emotion] += $this->lexicon[$word][$emotion];
                }
                $matchedWords[] = $word;
            }
        }

        // Normalisasi 0–100 berdasarkan jumlah kata
        $divisor = max($wordCount * 0.15, 1);
        $normalized = [];
        $total = 0;
        foreach ($scores as $emotion => $raw) {
            $val = min(100, round(($raw / $divisor) * 10));
            $normalized[$emotion] = $val;
            $total += $val;
        }

        // Fallback jika tidak ada kata cocok — neutral / debu
        if ($total === 0) {
            $normalized = ['joy' => 20, 'sadness' => 20, 'anger' => 10, 'fear' => 10, 'love' => 20];
        }

        // --- Tentukan emosi dominan ---
        arsort($normalized);
        $dominant = array_key_first($normalized);
        $moodData = $this->moodToHsl[$dominant];

        // --- Blending HSL berdasarkan secondary emotion ---
        $secondary = array_keys($normalized)[1];
        $secondaryHsl = $this->moodToHsl[$secondary];
        $w1 = $normalized[$dominant];
        $w2 = $normalized[$secondary];
        $wTotal = max($w1 + $w2, 1);

        $blendedH = round(($moodData['h'] * $w1 + $secondaryHsl['h'] * $w2) / $wTotal);
        $blendedS = round(($moodData['s'] * $w1 + $secondaryHsl['s'] * $w2) / $wTotal);
        $blendedL = round(($moodData['l'] * $w1 + $secondaryHsl['l'] * $w2) / $wTotal);

        // Clamp
        $blendedH = ($blendedH + 360) % 360;
        $blendedS = max(20, min(90, $blendedS));
        $blendedL = max(25, min(65, $blendedL));

        // --- Hex code ---
        $hexColor = $this->hslToHex($blendedH, $blendedS, $blendedL);

        // --- Generate palette (5 warna harmonis) ---
        $palette = $this->generatePalette($blendedH, $blendedS, $blendedL);

        // --- Intensity score ---
        $intensityScore = min(100, round($total / 2.5));
        $intensityLabel = match(true) {
            $intensityScore >= 80 => 'Shattering',
            $intensityScore >= 60 => 'Electric',
            $intensityScore >= 40 => 'Trembling',
            $intensityScore >= 20 => 'Smoldering',
            default               => 'Whisper',
        };

        // --- Keywords dari matched words ---
        $keywords = array_unique($matchedWords);
        $keywords = array_slice($keywords, 0, 6);
        if (count($keywords) < 3) {
            $keywords = array_merge($keywords, ['ethereal', 'raw', 'untold']);
            $keywords = array_unique($keywords);
        }

        return response()->json([
            'dominant_mood'   => $moodData['mood'],
            'tagline'         => $moodData['label'],
            'hsl'             => ['h' => $blendedH, 's' => $blendedS, 'l' => $blendedL],
            'hex'             => $hexColor,
            'palette'         => $palette,
            'emotions'        => $normalized,
            'keywords'        => array_values($keywords),
            'intensity_score' => $intensityScore,
            'intensity_label' => $intensityLabel,
            'matched_words'   => count($matchedWords),
            'word_count'      => $wordCount,
        ]);
    }

    private function hslToHex(int $h, int $s, int $l): string
    {
        $s /= 100;
        $l /= 100;
        $a = $s * min($l, 1 - $l);

        $f = function (int $n) use ($h, $l, $a): string {
            $k = ($n + $h / 30) % 12;
            // TYPO TELAH DIPERBAIKI DI SINI ($a)
            $color = $l - $a * max(min($k - 3, 9 - $k, 1), -1);
            return str_pad(dechex((int) round($color * 255)), 2, '0', STR_PAD_LEFT);
        };

        return '#' . $f(0) . $f(8) . $f(4);
    }

    private function generatePalette(int $h, int $s, int $l): array
    {
        $stops = [
            ['h' => ($h + 30) % 360, 's' => min($s + 10, 90), 'l' => min($l + 15, 80)],
            ['h' => $h,               's' => $s,               'l' => $l],
            ['h' => ($h + 180) % 360, 's' => max($s - 10, 20), 'l' => min($l + 20, 80)],
            ['h' => ($h + 60)  % 360, 's' => $s,               'l' => max($l - 15, 15)],
            ['h' => ($h + 300) % 360, 's' => max($s - 20, 20), 'l' => min($l + 25, 80)],
        ];

        return array_map(function ($stop) {
            return array_merge($stop, [
                'hex' => $this->hslToHex($stop['h'], $stop['s'], $stop['l'])
            ]);
        }, $stops);
    }
}

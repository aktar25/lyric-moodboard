<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lyric Moodboard</title>

    {{-- Tailwind CDN (ganti dengan vite build di production) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Urbanist font --}}
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { urbanist: ['Urbanist', 'sans-serif'] },
                    colors: {
                        surface: {
                            DEFAULT: '#111111',
                            2: '#161616',
                            3: '#1c1c1c',
                        },
                        accent: {
                            yellow: '#e8ff6b',
                            pink:   '#ff6b9d',
                            cyan:   '#6be8ff',
                        }
                    },
                    borderColor: { border: '#ffffff0f', border2: '#ffffff18' },
                    animation: {
                        'fade-up': 'fadeUp 0.6s ease forwards',
                    },
                    keyframes: {
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        * { box-sizing: border-box; }
        body { background: #0a0a0a; font-family: 'Urbanist', sans-serif; }

        .gradient-text {
            background: linear-gradient(135deg, #e8ff6b, #ff6b9d, #6be8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card {
            background: #111111;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .card-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #444;
            margin-bottom: 10px;
        }

        /* Bento Grid */
        .bento {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 12px;
        }
        .col-5 { grid-column: span 5; }
        .col-7 { grid-column: span 7; }
        .col-4 { grid-column: span 4; }
        .col-8 { grid-column: span 8; }
        .row-2 { grid-row: span 2; }

        @media (max-width: 768px) {
            .bento { grid-template-columns: 1fr; }
            .col-5, .col-7, .col-4, .col-8 { grid-column: span 1; }
            .row-2 { grid-row: span 1; }
        }

        /* Bar animation */
        .bar-fill { transition: width 1.2s cubic-bezier(0.16,1,0.3,1); }

        /* Color swatch */
        .swatch {
            flex: 1;
            height: 56px;
            border-radius: 10px;
            position: relative;
            transition: transform 0.2s;
            cursor: default;
        }
        .swatch:hover { transform: scaleY(1.08); }
        .swatch-hex {
            position: absolute;
            bottom: 4px; left: 0; right: 0;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.05em;
            mix-blend-mode: difference;
            color: white;
            opacity: 0.8;
        }

        /* Color fill overlay */
        .color-fill {
            position: absolute; inset: 0;
            border-radius: 20px;
            transition: background 1s ease;
        }
        .color-fill::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
            border-radius: 20px;
        }

        /* Mood tag */
        .mood-tag {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        /* Input */
        textarea {
            background: transparent;
            border: none;
            outline: none;
            color: #f0f0f0;
            font-family: 'Urbanist', sans-serif;
            font-size: 15px;
            line-height: 1.7;
            resize: none;
            width: 100%;
        }
        textarea::placeholder { color: #444; }

        /* Score ring */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.6s linear infinite; }
    </style>
</head>
<body class="min-h-screen text-[#f0f0f0] p-8">

<div class="max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="mb-8">
        <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-[#444] mb-1">Laravel × Mood Engine</p>
        <h1 class="gradient-text text-5xl font-black tracking-[-2px] leading-none mb-1">Lyric<br>Moodboard</h1>
        <p class="text-[#666] text-sm font-normal mt-2 tracking-[0.01em]">Drop your lyrics. Get your vibe. ✦</p>
    </div>

    {{-- Input Zone --}}
    <div class="card mb-7" style="border-color: rgba(255,255,255,0.1)">
        <textarea
            id="lyricsInput"
            rows="5"
            maxlength="2000"
            placeholder="Paste your lyrics here...&#10;&#10;'Cause baby, now we got bad blood...&#10;You know it used to be mad love..."
        ></textarea>
        <div class="flex items-center justify-between mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.06)">
            <span id="charCount" class="text-xs text-[#444] font-medium">0 / 2000</span>
            <button
                id="analyzeBtn"
                onclick="analyzeLyrics()"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-[13px] uppercase tracking-widest text-[#0a0a0a] cursor-pointer"
                style="background: #e8ff6b; transition: all 0.2s"
                onmouseover="this.style.filter='brightness(1.1)'"
                onmouseout="this.style.filter=''"
            >
                <svg id="spinnerIcon" class="hidden w-3.5 h-3.5 spinner" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="#0a0a0a" stroke-width="4"/>
                    <path class="opacity-75" fill="#0a0a0a" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span id="btnText">Analyze</span>
            </button>
        </div>
    </div>

    {{-- Error --}}
    <div id="errorMsg" class="hidden mb-5 px-4 py-3 rounded-xl text-sm text-red-400"
         style="background:#2a0a0a; border:1px solid #ff4444">
    </div>

    {{-- Bento Grid --}}
    <div id="bentoGrid" class="bento opacity-0 transition-opacity duration-700">

        {{-- Empty state --}}
        <div id="emptyState" class="col-5 col-7 text-center py-16 text-[#333]" style="grid-column: span 12">
            <div class="text-5xl mb-4 opacity-30">◈</div>
            <div class="text-sm font-semibold tracking-widest uppercase">Your moodboard will appear here</div>
        </div>

        {{-- [A] Primary Color Box (col 5, row 2) --}}
        <div id="boxColor" class="card col-5 row-2 hidden" style="min-height:240px; display:flex; flex-direction:column; justify-content:flex-end">
            <div id="colorFill" class="color-fill"></div>
            <div style="position:relative;z-index:1">
                <div class="card-label" style="color:rgba(255,255,255,0.3)">Primary Color</div>
                <div id="hexBig" class="text-3xl font-bold tracking-tight" style="font-variant-numeric:tabular-nums"></div>
                <div id="hslSub" class="text-xs mt-1" style="color:rgba(255,255,255,0.4)"></div>
            </div>
        </div>

        {{-- [B] Dominant Mood (col 7) --}}
        <div id="boxMood" class="card col-7 hidden" style="background:#161616">
            <div class="card-label">Dominant Mood</div>
            <div id="moodWord" class="font-black leading-none mt-1" style="font-size:52px;letter-spacing:-2px"></div>
            <div id="moodTagline" class="text-sm mt-3 font-normal leading-relaxed" style="color:#666;max-width:380px"></div>
        </div>

        {{-- [C] Palette (col 7) --}}
        <div id="boxPalette" class="card col-7 hidden" style="background:#161616">
            <div class="card-label">Color Palette</div>
            <div id="paletteRow" class="flex gap-2 mt-1"></div>
        </div>

        {{-- [D] Emotion Scores (col 5) --}}
        <div id="boxEmotions" class="card col-5 hidden" style="background:#1c1c1c">
            <div class="card-label">Emotion Scores</div>
            <div id="emotionBars" class="flex flex-col gap-2.5 mt-1"></div>
        </div>

        {{-- [E] HSL Values (col 4) --}}
        <div id="boxHsl" class="card col-4 hidden" style="background:#1c1c1c">
            <div class="card-label">HSL Values</div>
            <div id="hslH" class="font-extrabold mt-1" style="font-size:30px;letter-spacing:-1px"></div>
            <div class="text-[11px] tracking-widest uppercase mt-0.5" style="color:#444">Hue</div>
            <div id="hslSL" class="font-extrabold mt-2" style="font-size:20px;letter-spacing:-0.5px"></div>
            <div class="text-[11px] tracking-widest uppercase mt-0.5" style="color:#444">Saturation · Lightness</div>
        </div>

        {{-- [F] Keywords (col 8) --}}
        <div id="boxKeywords" class="card col-8 hidden" style="background:#161616">
            <div class="card-label">Mood Keywords</div>
            <div id="keywordTags" class="flex flex-wrap gap-2 mt-2"></div>
        </div>

        {{-- [G] Intensity Score (col 4) --}}
        <div id="boxIntensity" class="card col-4 hidden text-center flex flex-col items-center justify-center" style="background:#1c1c1c">
            <div class="card-label" style="text-align:center">Intensity</div>
            <div id="scoreRing" class="my-2"></div>
            <div id="scoreNum" class="font-black" style="font-size:28px;letter-spacing:-1px"></div>
            <div id="scoreLabel" class="text-xs tracking-widest uppercase mt-1" style="color:#555"></div>
        </div>

    </div>

    {{-- Footer --}}
    <p class="text-center text-xs mt-8" style="color:#333">
        Powered by Laravel 11 · Lexicon Emotion Engine · Tailwind CSS
    </p>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const textarea = document.getElementById('lyricsInput');
const charCount = document.getElementById('charCount');

textarea.addEventListener('input', () => {
    charCount.textContent = `${textarea.value.length} / 2000`;
});

const emotionColors = {
    joy:     '#e8ff6b',
    sadness: '#6baeff',
    anger:   '#ff6b6b',
    fear:    '#b56bff',
    love:    '#ff6bb5',
};

function hslToHex(h, s, l) {
    s /= 100; l /= 100;
    const a = s * Math.min(l, 1 - l);
    const f = n => {
        const k = (n + h / 30) % 12;
        const c = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
        return Math.round(255 * c).toString(16).padStart(2, '0');
    };
    return `#${f(0)}${f(8)}${f(4)}`;
}

function getTagStyle(word) {
    const w = word.toLowerCase();
    if (['sad','tears','broken','cry','lost','alone','sorrow','lonely'].includes(w))
        return 'background:#0d1f3a;border-color:#1a3a6e;color:#6baeff';
    if (['happy','joy','dance','alive','paradise','celebrate','bright'].includes(w))
        return 'background:#2a1f00;border-color:#6e4800;color:#ffb733';
    if (['hate','rage','fire','fight','burn','war'].includes(w))
        return 'background:#2a0a0a;border-color:#6e1a1a;color:#ff6b6b';
    if (['love','heart','kiss','angel','beautiful','desire','baby','darling','sweet','soul','forever'].includes(w))
        return 'background:#2a0a1f;border-color:#6e1a4a;color:#ff6bb5';
    if (['afraid','scared','nightmare','ghost','cold','hide','shadow','dark'].includes(w))
        return 'background:#1a0a2a;border-color:#4a1a6e;color:#b56bff';
    return 'background:#1a1a1a;border-color:#333;color:#888';
}

async function analyzeLyrics() {
    const lyrics = textarea.value.trim();
    if (!lyrics || lyrics.length < 10) return;

    const btn = document.getElementById('analyzeBtn');
    const spinner = document.getElementById('spinnerIcon');
    const btnText = document.getElementById('btnText');
    const errorMsg = document.getElementById('errorMsg');
    const bentoGrid = document.getElementById('bentoGrid');

    btn.disabled = true;
    spinner.classList.remove('hidden');
    btnText.textContent = 'Analyzing...';
    errorMsg.classList.add('hidden');
    bentoGrid.style.opacity = '0';

    try {
        const res = await fetch('/analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ lyrics })
        });

        if (!res.ok) throw new Error(`Server error: ${res.status}`);
        const data = await res.json();
        renderMoodboard(data);

    } catch (err) {
        errorMsg.textContent = `Analisis gagal: ${err.message}. Silakan coba lagi.`;
        errorMsg.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        spinner.classList.add('hidden');
        btnText.textContent = 'Analyze';
    }
}

function renderMoodboard(r) {
    const { h, s, l } = r.hsl;
    const bentoGrid = document.getElementById('bentoGrid');

    // Hide empty state
    document.getElementById('emptyState').style.display = 'none';

    // Show & fill each card
    const showCard = id => {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        el.style.display = '';
        return el;
    };

    // [A] Primary Color
    showCard('boxColor');
    document.getElementById('colorFill').style.background = `hsl(${h},${s}%,${l}%)`;
    document.getElementById('hexBig').textContent = r.hex;
    document.getElementById('hslSub').textContent = `hsl(${h}, ${s}%, ${l}%)`;

    // [B] Dominant Mood
    showCard('boxMood');
    document.getElementById('moodWord').textContent = r.dominant_mood;
    document.getElementById('moodTagline').textContent = r.tagline;

    // [C] Palette
    showCard('boxPalette');
    const paletteRow = document.getElementById('paletteRow');
    paletteRow.innerHTML = r.palette.map(p =>
        `<div class="swatch" style="background:hsl(${p.h},${p.s}%,${p.l}%)">
            <div class="swatch-hex">${p.hex}</div>
        </div>`
    ).join('');

    // [D] Emotion Bars
    showCard('boxEmotions');
    document.getElementById('emotionBars').innerHTML = Object.entries(r.emotions).map(([k, v]) =>
        `<div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:11px;font-weight:600;color:#555;width:56px;text-transform:uppercase;letter-spacing:0.08em">${k}</div>
            <div style="flex:1;height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden">
                <div class="bar-fill" style="height:100%;width:0%;border-radius:2px;background:${emotionColors[k] || '#888'}"
                     data-width="${v}%"></div>
            </div>
            <div style="font-size:11px;font-weight:700;color:#555;width:26px;text-align:right">${v}</div>
        </div>`
    ).join('');

    // [E] HSL Values
    showCard('boxHsl');
    document.getElementById('hslH').style.color = `hsl(${h},${s}%,${Math.min(l + 20, 80)}%)`;
    document.getElementById('hslH').textContent = `${h}°`;
    document.getElementById('hslSL').style.color = `hsl(${h},${s}%,${Math.min(l + 20, 80)}%)`;
    document.getElementById('hslSL').textContent = `${s}% · ${l}%`;

    // [F] Keywords
    showCard('boxKeywords');
    document.getElementById('keywordTags').innerHTML = r.keywords.map(kw =>
        `<div class="mood-tag" style="${getTagStyle(kw)}">${kw}</div>`
    ).join('');

    // [G] Score Ring
    showCard('boxIntensity');
    const pct = r.intensity_score;
    const radius = 34, cx = 40, cy = 40;
    const circ = 2 * Math.PI * radius;
    const dash = (pct / 100) * circ;
    document.getElementById('scoreRing').innerHTML =
        `<svg viewBox="0 0 80 80" width="80" height="80">
            <circle cx="${cx}" cy="${cy}" r="${radius}" fill="none" stroke="#1c1c1c" stroke-width="6"/>
            <circle cx="${cx}" cy="${cy}" r="${radius}" fill="none" stroke="${r.hex}" stroke-width="6"
                stroke-dasharray="${dash.toFixed(1)} ${circ.toFixed(1)}"
                stroke-linecap="round" transform="rotate(-90 ${cx} ${cy})"
                style="transition:stroke-dasharray 1.5s cubic-bezier(.16,1,.3,1)"/>
        </svg>`;
    document.getElementById('scoreNum').textContent = pct;
    document.getElementById('scoreNum').style.color = r.hex;
    document.getElementById('scoreLabel').textContent = r.intensity_label;

    // Fade in grid
    requestAnimationFrame(() => {
        bentoGrid.style.opacity = '1';
        // Animate bars after a tick
        setTimeout(() => {
            document.querySelectorAll('.bar-fill[data-width]').forEach(el => {
                el.style.width = el.dataset.width;
            });
        }, 100);
    });
}

// Ctrl+Enter shortcut
textarea.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') analyzeLyrics();
});
</script>
</body>
</html>

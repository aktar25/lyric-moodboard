<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class LyricCache extends Model
{
    use HasFactory;

    protected $fillable = ['lyrics_hash', 'raw_lyrics', 'ai_scores'];

    protected $casts = [
        'ai_scores' => 'array', // Mengubah JSON dari database menjadi Array otomatis
    ];
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lyric_caches', function (Blueprint $table) {
            $table->id();
            $table->string('lyrics_hash')->unique(); // Untuk menyimpan 'sidik jari' lirik
            $table->text('raw_lyrics');              // Teks lirik aslinya
            $table->json('ai_scores');               // Menyimpan JSON skor emosi dari Google
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lyric_caches');
    }
};

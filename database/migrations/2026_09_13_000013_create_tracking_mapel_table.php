<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_mapel', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->decimal('theta_estimasi', 5, 3)->nullable();
            $table->string('level_kompetensi', 30)->nullable();
            $table->integer('total_tryout_diikuti')->default(0);
            $table->decimal('rata_rata_skor_irt', 5, 3)->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'mapel_id']);
            $table->index('mapel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_mapel');
    }
};
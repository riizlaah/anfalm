<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_kompetensi', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kompetensi_dasar_id')->constrained('kompetensi_dasar')->cascadeOnDelete();
            $table->integer('total_soal_dikerjakan')->default(0);
            $table->integer('total_benar')->default(0);
            $table->decimal('persentase_benar', 5, 2)->default(0);
            $table->decimal('theta_estimasi', 5, 3)->nullable();
            $table->decimal('theta_se', 5, 3)->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'kompetensi_dasar_id']);
            $table->index('kompetensi_dasar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_kompetensi');
    }
};
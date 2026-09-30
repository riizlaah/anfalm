<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('detail_paket_soal')
            ->leftJoin('paket_soal', 'paket_soal.id', '=', 'detail_paket_soal.paket_soal_id')
            ->whereNotNull('paket_soal.deleted_at')
            ->delete();
    }

    public function down(): void
    {
        //
    }
};

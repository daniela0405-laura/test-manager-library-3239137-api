<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T-18: las invariantes "solo dominio" de data-model.md §4 pasan a ser del motor.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE book ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1)');
        DB::statement("ALTER TABLE \"user\" ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE book DROP CONSTRAINT IF EXISTS ck_book_total_positive');
        DB::statement('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS ck_user_role');
    }
};
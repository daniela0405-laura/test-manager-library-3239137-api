<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Traduce data-model.md §3 y §4: seis tablas, singular, UUID, sin DEFAULT, sin created_at/updated_at.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');

        Schema::create('category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
        });

        // §9: las cinco categorías van en la migración inicial con ids fijos.
        DB::table('category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);

        Schema::create('user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });

        Schema::create('book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->foreignUuid('category_id')->constrained('category')->restrictOnDelete();
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX ux_book_isbn_active ON book (isbn) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX ix_book_category_title ON book (category_id, title) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)');

        Schema::create('member', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });

        Schema::create('loan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('member')->restrictOnDelete();
            $table->foreignUuid('loaned_by_user_id')->constrained('user')->restrictOnDelete();
            $table->timestampTz('loaned_at');
            $table->index('loaned_at');
        });

        Schema::create('loan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_id')->constrained('loan')->cascadeOnDelete();
            $table->foreignUuid('book_id')->constrained('book')->restrictOnDelete();
            $table->string('book_title', 250);
            $table->char('isbn', 13);
            $table->string('category_name', 120); // congelado, sin FK a propósito
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            $table->unique(['loan_id', 'book_id']);
            $table->index('book_id');
        });
        DB::statement('CREATE INDEX ix_loan_item_overdue ON loan_item (due_at) WHERE returned_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_item');
        Schema::dropIfExists('loan');
        Schema::dropIfExists('member');
        Schema::dropIfExists('book');
        Schema::dropIfExists('user');
        Schema::dropIfExists('category');
    }
};
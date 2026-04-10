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
        Schema::table('products', function (Blueprint $table) {
            $table->string('marca')->nullable()->index()->after('precio');
            $table->string('categoria')->nullable()->index()->after('marca');
        });

        // Removed fulltext indexes since we migrated to Postgres and pgvector
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText(['nombre', 'descripcion', 'marca', 'categoria']);
            $table->fullText(['nombre', 'descripcion']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['marca', 'categoria']);
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement('ALTER TABLE products ADD FULLTEXT search_index(nombre, descripcion, marca, categoria)');
    }

    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('search_index');
        });
    }
};

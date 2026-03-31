<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id')->nullable();
            $table->json('items')->after('lead_id')->nullable();
            
            // Adjust defaults for the new flow if not present, total_amount already exists in DB but let's ensure:
            if (!Schema::hasColumn('orders', 'total')) {
                $table->decimal('total', 10, 2)->default(0)->after('items');
            }
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'items', 'total']);
        });
    }
};

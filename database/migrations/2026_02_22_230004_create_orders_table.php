<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->json('guest_data')->nullable(); // name, address, phone, NIT/RUC
            $table->enum('payment_method', ['manual_qr', 'gateway_stripe', 'gateway_mercadopago']);
            $table->enum('type', ['formal', 'informal'])->default('informal');
            $table->string('status'); // pending_payment, paid, shipped, cancelled
            $table->string('session_uuid')->nullable()->index();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

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
        Schema::create('qrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('external_qr_id', 100)->unique();
            $table->text('qr')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['new', 'paid', 'expired'])->default('new');
            $table->string('donor_name')->nullable();
            $table->string('voucher_id', 100)->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->longText('bnb_blob')->nullable();
            $table->dateTime('expiration_date')->nullable();
            $table->timestamps();

            // Indexes for faster lookups
            $table->index('status');
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qrs');
    }

};

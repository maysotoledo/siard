<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('mercado_pago');
            $table->string('external_reference', 100)->unique();
            $table->string('mercado_pago_payment_id', 40)->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('status', 40)->default('pending');
            $table->string('status_detail', 120)->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->longText('qr_code_base64')->nullable();
            $table->string('ticket_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_payment_requests');
    }
};

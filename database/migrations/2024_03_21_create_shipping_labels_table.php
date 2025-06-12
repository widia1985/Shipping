<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipping_labels', function (Blueprint $table) {
            $table->id();
            $table->string('carrier');
            $table->string('account_number');
            $table->string('tracking_number')->unique();
            $table->string('invoice_number')->nullable();
            $table->string('service_type');
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->string('label_url')->nullable();
            $table->json('label_data')->nullable();
            $table->json('shipper_info');
            $table->json('recipient_info');
            $table->json('package_info');
            $table->string('status')->default('ACTIVE');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['carrier', 'account_number']);
            $table->index('invoice_number');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipping_labels');
    }
}; 
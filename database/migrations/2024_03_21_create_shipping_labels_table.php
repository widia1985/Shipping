<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('shipping_labels', function (Blueprint $table) {
            $table->id();
            $table->string('carrier');
            $table->string('account_number');
            $table->string('account_name', 50)->nullable();
            $table->string('tracking_number')->unique();
            $table->string('invoice_number')->nullable();
            $table->string('service_type');
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->string('label_url')->nullable();
            $table->string('image_format', 50)->nullable();
            $table->json('label_data')->nullable();
            $table->json('shipper_info');
            $table->json('recipient_info');
            $table->json('package_info');
            $table->string('status')->default('ACTIVE');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('formdata')->nullable();
            $table->string('customer_po_number', 50)->nullable();
            $table->string('market_order_id', 50)->nullable();
            $table->integer('box_id')->default(0);
            $table->text('shipmentfees')->nullable();
            $table->text('packagefees')->nullable();
            $table->decimal('package_ahs', 8, 3)->default(0);
            $table->decimal('shipping_cost_base', 8, 3)->default(0);
            $table->tinyInteger('is_return')->default(0);
            $table->string('rma_number', 50)->default('');
            $table->text('invoices')->nullable();

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

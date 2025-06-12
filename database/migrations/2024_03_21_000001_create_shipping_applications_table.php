<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipping_applications', function (Blueprint $table) {
            $table->id();
            $table->string('accountname');
            $table->string('application_id');
            $table->string('shared_secret');
            $table->string('carrier');
            $table->boolean('sandbox')->default(true);
            $table->timestamps();

            $table->unique(['accountname', 'carrier']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipping_applications');
    }
}; 
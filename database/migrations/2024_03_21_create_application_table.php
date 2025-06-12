<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 50)->nullable();
            $table->string('application_id', 100)->nullable();
            $table->string('shared_secret', 100)->nullable();
            $table->string('application_name', 50)->nullable();

            $table->index('type');
            $table->index('application_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('application');
    }
}; 
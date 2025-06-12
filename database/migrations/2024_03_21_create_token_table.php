<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('token', function (Blueprint $table) {
            $table->increments('token_id');
            $table->integer('app_id')->nullable();
            $table->text('access_token')->nullable();
            $table->dateTime('expires_time')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('accountname', 50)->nullable();

            $table->index('app_id');
            $table->index('accountname');
        });
    }

    public function down()
    {
        Schema::dropIfExists('token');
    }
}; 
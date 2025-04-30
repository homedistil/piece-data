<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModelsSyncQueueTable extends Migration
{
    public function up()
    {
        Schema::create('sync_models_queue', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('handler_name', 255);
            $table->string('slave_name', 255);
            $table->string('model_name', 255);
            $table->string('model_id', 255);
            $table->text('model_data');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_models_queue');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestModelsTable extends Migration
{
    public function up()
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name');
            //$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_models');
    }
}

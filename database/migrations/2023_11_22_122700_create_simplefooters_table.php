<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class CreatesimplefootersTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::dropIfExists('simplefooters');
    Schema::create('simplefooters', function (Blueprint $table) {
      $table->id();
      $table->timestamp('timestamp')->useCurrent()->onUpdate(null);
      
      $table->string('status')->nullable();
      $table->string('text')->nullable();
      $table->string('fontsize')->nullable();
      $table->string('fontcolor')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('simplefooters');
  }
}
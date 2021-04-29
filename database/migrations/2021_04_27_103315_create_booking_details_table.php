<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
			$table->timestamp('arrival_date')->default('0000-00-00 00:00:00');
			$table->timestamp('departure_date')->default('0000-00-00 00:00:00');
			$table->string('surname', 100);
			$table->string('given', 100);
			$table->string('email', 100);
			$table->integer('adults')->default(0);
			$table->integer('area_id')->default(0);
			$table->integer('category_id')->default(0);
			$table->integer('children')->default(0);
			$table->integer('infants')->default(0);
			$table->longText('notes');
			$table->Text('address');
			$table->integer('rate_type_id')->default(0);
			$table->string('state', 100);
			$table->string('town', 100);
			$table->integer('country_id')->default(0);
			$table->integer('nights')->default(0);
			$table->string('phone', 100);
			$table->string('post_code', 100);
			$table->integer('pets')->default(0);
			$table->integer('guest_id')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('booking_details');
    }
}

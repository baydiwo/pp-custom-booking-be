<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_details', function (Blueprint $table) {
            $table->id();
			$table->integer('property_id');
			$table->boolean('allow_group_bookings')->default(0);
			$table->boolean('children_allowed')->default(0);
			$table->string('currency', 100);
			$table->string('currency_symbol', 100);
			$table->timestamp('default_arrival_time');
			$table->timestamp('default_depart_time');
			$table->integer('gateway_id')->default(0);
			$table->string('latitude', 100);
			$table->string('longitude', 100);
			$table->string('max_child_age', 100);
			$table->string('max_infant_age', 100);
			$table->integer('min_age_required_to_book')->default(0);
			$table->boolean('pets_allowed')->default(0);
			$table->string('redirection_url', 100);
			$table->boolean('smoking_allowed')->default(0);
			$table->integer('max_group_bookings')->default(0);
			$table->string('google_analytics_code', 100);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_details');
    }
}

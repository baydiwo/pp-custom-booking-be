<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAreaDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('area_details', function (Blueprint $table) {
            $table->id();
			$table->integer('property_id');
			$table->integer('area_id');
			$table->integer('category_id')->default(0);
			$table->string('name', 100)->default(null);;
			$table->Text('address_line1')->default(null);;
			$table->Text('address_line2')->default(null);;
			$table->Text('address_line3')->default(null);;
			$table->string('town', 100)->default(null);;
			$table->string('state', 100)->default(null);;
			$table->integer('post_code')->default(0);
			$table->string('external_ref', 100)->default(null);;
			$table->string('clean_status', 100)->default(null);;
			$table->longText('description')->default(null);;
			$table->string('extension', 100)->default(null);;
			$table->longText('guest_description')->default(null);;
			$table->integer('max_occupants')->default(0);
			$table->timestamp('created_date')->default('0000-00-00 00:00:00');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('area_details');
    }
}

<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
class CreateLmconfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lmconfig', function (Blueprint $table) {
            
            $table->increments('id');
            $table->string('rootURL')->nullable();
            $table->string('licenseKey')->nullable();
            $table->string('clientEmail')->nullable();
            $table->string('productKey')->nullable();
            $table->text('LCD')->nullable();
            $table->text('LRD')->nullable();
            $table->text('installationKey')->nullable();
            $table->text('installationHash')->nullable();
            $table->dateTime('lastCheckedOn')->nullable();
            $table->dateTime('expireOn')->nullable();
            $table->dateTime('supportTill')->nullable();
            $table->dateTime('FailedAttempts')->default(0);
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
        Schema::dropIfExists('lmconfig');
    }
}
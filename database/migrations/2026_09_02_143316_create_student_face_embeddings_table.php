<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentFaceEmbeddingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_face_embeddings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('studentId');
            $table->integer('tenantId');
            $table->integer('locationId');
            $table->longText('embedding');
            $table->string('sampleLabel', 20)->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->string('createdBy', 191)->nullable();

            $table->foreign('studentId')
                ->references('id')->on('student')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('studentId');
            $table->index(['tenantId', 'locationId']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_face_embeddings');
    }
}

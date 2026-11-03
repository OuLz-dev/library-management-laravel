<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id('books_id');
            $table->foreignId('category_id')->nullable()->constrained('categories', 'categories_id')->onDelete('set null');
            $table->string('title', 255);
            $table->string('author', 255)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->integer('year')->nullable();
            $table->string('isbn', 20)->unique();
            $table->string('cover')->nullable();
            $table->integer('stock')->default(1);
            $table->integer('available_stock')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('books');
    }
};
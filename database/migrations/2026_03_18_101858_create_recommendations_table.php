<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('user')
                ->onDelete('cascade');
            $table->foreignId('plat_id')
                ->constrained('plat')
                ->onDelete('cascade');
            $table->integer('score');
            $table->text('warning_message');
            $table->enum('status', ['processing', 'ready'])->default('processing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('source_url')->unique();
            $table->longText('original_content');
            $table->text('ai_summary')->nullable();
            $table->json('ai_tags')->nullable();
            $table->longText('ai_rewritten_content')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('last_ai_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

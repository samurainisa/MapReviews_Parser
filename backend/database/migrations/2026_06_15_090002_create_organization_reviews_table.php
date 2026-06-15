<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('source_review_id')->nullable();
            $table->string('author_name')->nullable();
            $table->date('review_date')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('hash', 64);

            $table->timestamps();

            $table->index('organization_id');
            $table->index('review_date');

            // Fallback-идентичность отзыва, защищает от дублей при повторном парсинге.
            $table->unique(['organization_id', 'hash']);

            // Уникальность по внешнему id, когда он доступен.
            $table->unique(['organization_id', 'source_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_reviews');
    }
};

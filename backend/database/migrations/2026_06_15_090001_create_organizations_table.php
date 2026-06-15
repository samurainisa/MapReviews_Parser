<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('source_url');
            $table->text('normalized_url')->nullable();

            $table->string('source_external_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('address')->nullable();

            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->unsignedInteger('loaded_reviews_count')->default(0);

            $table->string('parse_status')->default('not_started')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_parsed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'parse_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

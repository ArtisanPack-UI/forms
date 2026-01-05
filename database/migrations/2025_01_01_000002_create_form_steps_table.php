<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Steps Table Migration
 *
 * Creates the form_steps table for multi-step forms.
 * Each step defines a page/section with navigation settings.
 *
 * @since   1.0.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('form_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            // Step Info
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // Ordering
            $table->unsignedInteger('sort_order')->default(0);

            // Navigation
            $table->string('next_button_text', 100)->default('Next');
            $table->string('prev_button_text', 100)->default('Previous');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('form_id');
            $table->index(['form_id', 'sort_order']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('form_steps');
    }
};

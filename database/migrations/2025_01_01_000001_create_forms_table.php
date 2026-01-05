<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Forms Table Migration
 *
 * Creates the forms table for storing main form definitions including
 * basic info, display settings, multi-step configuration, and status.
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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Display Settings
            $table->string('submit_button_text', 100)->default('Submit');
            $table->text('success_message')->nullable();
            $table->string('redirect_url', 500)->nullable();

            // Form Settings (JSON)
            $table->json('settings')->nullable();

            // Multi-step Configuration
            $table->boolean('is_multi_step')->default(false);
            $table->boolean('show_progress_bar')->default(true);
            $table->boolean('allow_step_navigation')->default(false);

            // Status
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('is_active');
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};

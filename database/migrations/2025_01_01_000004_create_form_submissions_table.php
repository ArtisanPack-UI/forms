<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Submissions Table Migration
 *
 * Creates the form_submissions table for storing header records
 * for each form submission, including metadata and status flags.
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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            // Submission Metadata
            $table->string('submission_number', 50);

            // Request Info
            $table->string('page_url', 500)->nullable();
            $table->string('referrer_url', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Status
            $table->boolean('is_read')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_starred')->default(false);

            // Notes (admin can add notes)
            $table->text('admin_notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('form_id');
            $table->index('is_read');
            $table->index('is_spam');
            $table->index('created_at');
            $table->index('submission_number');
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};

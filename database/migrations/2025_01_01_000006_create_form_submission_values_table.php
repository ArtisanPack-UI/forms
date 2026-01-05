<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Submission Values Table Migration
 *
 * Creates the form_submission_values table for storing individual
 * field values for each form submission. Field reference is denormalized
 * to preserve data even if the original field is deleted.
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
        Schema::create('form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('form_fields')->nullOnDelete();

            // Field Reference (denormalized for deleted fields)
            $table->string('field_name', 100);
            $table->string('field_label')->nullable();
            $table->string('field_type', 50);

            // Value Storage
            $table->text('value')->nullable();
            $table->json('value_array')->nullable();

            // File Reference (if field type is file)
            $table->foreignId('upload_id')->nullable()->constrained('form_uploads')->nullOnDelete();

            // Timestamps
            $table->timestamp('created_at')->nullable();

            // Indexes
            $table->index('submission_id');
            $table->index('field_id');
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submission_values');
    }
};

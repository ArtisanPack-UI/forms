<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Uploads Table Migration
 *
 * Creates the form_uploads table for storing metadata about
 * files uploaded through form submissions.
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
        Schema::create('form_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('form_fields')->nullOnDelete();

            // File Info
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 50)->default('form-uploads');
            $table->string('path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size');

            // Timestamps
            $table->timestamps();

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
        Schema::dropIfExists('form_uploads');
    }
};

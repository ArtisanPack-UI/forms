<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Notifications Table Migration
 *
 * Creates the form_notifications table for storing email notification
 * configurations per form. Supports admin notifications, autoresponders,
 * and custom notifications with conditional sending.
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
        Schema::create('form_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            // Notification Type
            $table->string('type', 50);
            $table->string('name');

            // Recipients
            $table->string('to_email', 500)->nullable();
            $table->string('to_field', 100)->nullable();
            $table->string('cc_emails', 500)->nullable();
            $table->string('bcc_emails', 500)->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_field', 100)->nullable();

            // Email Content
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject', 500);
            $table->text('message');

            // Conditional Sending
            $table->json('conditional_logic')->nullable();

            // Settings
            $table->boolean('include_submission_data')->default(true);
            $table->boolean('is_active')->default(true);

            // Ordering
            $table->unsignedInteger('sort_order')->default(0);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('form_id');
            $table->index('type');
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
        Schema::dropIfExists('form_notifications');
    }
};

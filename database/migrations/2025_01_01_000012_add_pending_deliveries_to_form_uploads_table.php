<?php
/**
 * Add pending deliveries column to the form uploads table.
 *
 * Counts the notification and webhook jobs that still need a file uploaded
 * to a "Do not store" file field. The file and its upload record are deleted
 * once the count reaches zero (see TransientUploads); null for every other
 * upload.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.6.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add pending deliveries column migration class.
 *
 * @since 1.6.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.6.0
     */
    public function up(): void
    {
        Schema::table('form_uploads', function (Blueprint $table) {
            $table->unsignedInteger('pending_deliveries')->nullable()->after('size')->index();
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.6.0
     */
    public function down(): void
    {
        Schema::table('form_uploads', function (Blueprint $table) {
            $table->dropIndex(['pending_deliveries']);
            $table->dropColumn('pending_deliveries');
        });
    }
};

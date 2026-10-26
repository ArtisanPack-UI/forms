<?php
/**
 * Add media ID column to the form uploads table.
 *
 * Records the artisanpack-ui/media-library item a file field with Media
 * Library storage saved the upload as, so the upload points at the media
 * library's file and leaves it in place when it is deleted.
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
 * Add media ID column migration class.
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
            $table->unsignedBigInteger('media_id')->nullable()->after('field_id')->index();
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
            $table->dropIndex(['media_id']);
            $table->dropColumn('media_id');
        });
    }
};

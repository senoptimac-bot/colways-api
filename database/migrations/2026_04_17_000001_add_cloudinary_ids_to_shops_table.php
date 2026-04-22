<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes Cloudinary ID pour la gestion propre des images.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('avatar_cloudinary_id')->nullable()->after('avatar_url');
            $table->string('cover_cloudinary_id')->nullable()->after('cover_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['avatar_cloudinary_id', 'cover_cloudinary_id']);
        });
    }
};

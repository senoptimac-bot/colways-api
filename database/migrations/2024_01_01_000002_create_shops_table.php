<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table shops ("Mon étal" dans l'interface Colways).
     * Relation 1:1 avec users — un vendeur = un seul étal.
     * Le badge "Vendeur de Colobane ✓" est attribué manuellement par l'admin en V1.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();

            // Référence au propriétaire — UNIQUE : un seul étal par user
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Informations de l'étal
            $table->string('shop_name', 150);
            $table->text('description')->nullable();

            // Photos de l'étal (URLs Cloudinary)
            $table->string('avatar_url', 500)->nullable();
            $table->string('cover_url', 500)->nullable(); // Format 16:9

            // Localisation — quartiers de Dakar
            $table->enum('quartier', [
                'colobane',
                'hlm',
                'medina',
                'plateau',
                'grand_yoff',
                'parcelles',
                'pikine',
                'guediawaye',
                'autre',
            ]);

            // Badge "Vendeur de Colobane ✓" — attribué par admin, couleur Or Colways (#D97706)
            $table->boolean('is_colobane_verified')->default(false);
            $table->timestamp('colobane_verified_at')->nullable();

            // Compteur mis à jour par ArticleObserver
            $table->unsignedInteger('articles_count')->default(0);

            $table->timestamps();

            // Index pour les filtres du feed et de la carte des quartiers
            $table->index('user_id');
            $table->index('quartier');
            $table->index('is_colobane_verified');
        });
    }

    /**
     * Supprime la table shops.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};

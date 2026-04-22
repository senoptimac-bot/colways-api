<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table reports (signalements d'articles).
     * Accessible publiquement — reporter_id est nullable pour les non-connectés.
     * Rate limit : 3 signalements/heure/IP (géré dans ReportController).
     * Chaque signalement déclenche une notification email à l'admin.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Article signalé — supprimé en cascade si l'article est retiré
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // Utilisateur ayant signalé — nullable car accès public autorisé
            $table->foreignId('reporter_id')
                ->nullable()
                ->constrained('users');

            // Raison du signalement
            $table->enum('reason', [
                'arnaque',
                'article_inexistant',
                'contenu_inapproprie',
                'autre',
            ]);

            // Description optionnelle fournie par le signalant
            $table->text('description')->nullable();

            // Statut de traitement par l'admin
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');

            // Traçabilité admin
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            // Pas de updated_at — les signalements ne sont pas modifiés
            $table->timestamp('created_at')->nullable();

            // Index pour le tableau de bord admin
            $table->index('article_id');
            $table->index('status');
        });
    }

    /**
     * Supprime la table reports.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

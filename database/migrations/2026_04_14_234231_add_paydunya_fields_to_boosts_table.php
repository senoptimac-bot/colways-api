<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes pour l'intégration PayDunya.
     *
     * PayDunya = passerelle sénégalaise : Wave + Orange Money + Free Money + carte.
     *
     * Architecture "plug-and-play" :
     *   PAYDUNYA_MASTER_KEY dans .env → paiement automatique
     *   Pas de clé                    → fallback instructions manuelles
     */
    public function up(): void
    {
        Schema::table('boosts', function (Blueprint $table) {
            // Token de facture PayDunya — pour vérifier le statut du paiement
            $table->string('paydunya_token', 100)->nullable()->after('payment_ref');

            // URL de checkout — l'app ouvre ce lien dans le navigateur de l'utilisateur
            $table->string('payment_url', 500)->nullable()->after('paydunya_token');

            // Statut brut reçu via webhook PayDunya : completed | pending | cancelled | failed
            $table->string('paydunya_status', 30)->nullable()->after('payment_url');
        });
    }

    /**
     * Supprime les colonnes PayDunya.
     */
    public function down(): void
    {
        Schema::table('boosts', function (Blueprint $table) {
            $table->dropColumn(['paydunya_token', 'payment_url', 'paydunya_status']);
        });
    }
};

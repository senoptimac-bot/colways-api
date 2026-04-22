<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table users.
     * Identifiant de connexion : numéro de téléphone (pas email).
     * Le numéro WhatsApp est validé à l'inscription (+221XXXXXXXXX).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Informations de base
            $table->string('name', 100);

            // Numéro de téléphone — identifiant unique de connexion
            $table->string('phone', 20)->unique();

            // Numéro WhatsApp validé à l'inscription (format +221XXXXXXXXX)
            $table->string('whatsapp_number', 20)->nullable();
            $table->boolean('whatsapp_verified')->default(false);

            // Authentification
            $table->string('password');

            // Rôle : acheteur par défaut, vendeur quand il crée son étal, admin pour la gestion
            $table->enum('role', ['buyer', 'seller', 'admin'])->default('buyer');

            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index('phone');
            $table->index('role');
        });
    }

    /**
     * Supprime la table users.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

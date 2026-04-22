<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ────────────────────────────────────────────────────────────────────────────
 *  Sprint 13 — Le Gardien Friperie & l'Algorithme Intelligent
 * ────────────────────────────────────────────────────────────────────────────
 *
 *  Cette migration finalise la structure BDD pour le système à 5 piliers
 *  (Identité, Mérite, Découverte, Confiance, Amplification).
 *
 *  État actuel observé :
 *   - articles.friperie_score      → déjà présent (default 100)
 *   - articles.guardian_flags      → déjà présent
 *   - articles.published_at        → déjà présent
 *   - articles.status_new          → colonne orpheline à nettoyer
 *   - articles.status              → déjà varchar (bien)
 *   - reports.reason               → encore CHECK-constrained → à libérer
 *   - shops.trust_level            → à créer
 *
 *  Principe : code idempotent — vérifie l'état avant chaque modification,
 *  pour que la migration puisse tourner sans planter quel que soit l'état.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Nettoyer la colonne orpheline status_new sur articles ─────────
        if (Schema::hasColumn('articles', 'status_new')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('status_new');
            });
        }

        // ── 2. Ajouter les colonnes manquantes du Gardien Friperie sur articles ─────
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'friperie_score')) {
                $table->integer('friperie_score')->default(100)->after('status');
            }
            if (! Schema::hasColumn('articles', 'guardian_flags')) {
                $table->json('guardian_flags')->nullable()->after('friperie_score');
            }
            if (! Schema::hasColumn('articles', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('created_at');
            }
        });

        DB::statement('UPDATE articles SET published_at = created_at WHERE published_at IS NULL');

        // ── 3. Shops : niveau de confiance du vendeur ────────────────────────
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'trust_level')) {
                $table->string('trust_level', 20)
                      ->default('new')
                      ->after('is_colobane_verified')
                      ->comment('Niveau de confiance — new / trusted / flagged');
            }

            if (! Schema::hasColumn('shops', 'articles_approved_count')) {
                $table->unsignedInteger('articles_approved_count')->default(0);
            }

            if (! Schema::hasColumn('shops', 'articles_rejected_count')) {
                $table->unsignedInteger('articles_rejected_count')->default(0);
            }
        });

        // Index trust_level (safe car on vient de le créer si absent)
        try {
            Schema::table('shops', function (Blueprint $table) {
                $table->index('trust_level');
            });
        } catch (\Throwable $e) {
            // Index déjà présent — on ignore
        }

        // ── 4. Reports : libérer reason du CHECK constraint SQLite ───────────
        // On suit le pattern éprouvé de fix_condition_enum_to_string.php :
        // colonne temporaire → copie → drop ancienne → rename.
        // Cela permet d'accepter les nouvelles raisons :
        //   pas_de_la_friperie, prix_abusif, copie_article
        if (! Schema::hasColumn('reports', 'reason_new')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->string('reason_new', 40)->nullable()->after('reason');
            });

            DB::statement('UPDATE reports SET reason_new = reason');

            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('reason');
            });

            Schema::table('reports', function (Blueprint $table) {
                $table->renameColumn('reason_new', 'reason');
            });
        }

        // ── 5. Assurer un friperie_score de 100 pour les anciens articles ────
        // (Au cas où certains auraient été insérés avec NULL avant cette migration)
        DB::statement('UPDATE articles SET friperie_score = 100 WHERE friperie_score IS NULL');
    }

    public function down(): void
    {
        // Rollback minimal — on ne retouche pas les colonnes héritées d'autres
        // migrations. On retire uniquement ce qu'on a explicitement ajouté ici.
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'trust_level')) {
                try { $table->dropIndex(['trust_level']); } catch (\Throwable $e) {}
                $table->dropColumn(['trust_level', 'articles_approved_count', 'articles_rejected_count']);
            }
        });
    }
};

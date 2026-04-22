<?php

namespace Tests\Feature;

use App\Services\FriperieGuardianService;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  Tests unitaires — FriperieGuardianService
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Vérifie que le Gardien détecte correctement chaque scénario :
 *   ✅ Article friperie de qualité → publié directement
 *   🚫 Électronique détecté → pending_review
 *   🚫 Prix hors plafond → pending_review
 *   📉 Article bâclé → score faible (invisible mais pas bloqué)
 *   🔒 Vendeur suspect → toujours en review
 *   ✅ Vendeur de confiance → publié même sans analyse
 *
 *  Lancer : php artisan test --filter=FriperieGuardianTest
 */
class FriperieGuardianTest extends TestCase
{
    private FriperieGuardianService $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardian = new FriperieGuardianService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Cas nominaux — articles valides
    // ─────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_bon_article_friperie_est_publie_directement(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Nike Air Force 1 blanc très bon état',
            'description'  => 'Paire de Nike Air Force 1 coloris blanc. Très bon état, semelles propres, légères traces d\'usage normales.',
            'price'        => 15000,
            'category'     => 'chaussures',
            'condition'    => 'tres_bon_etat',
            'images_count' => 3,
        ], 'new');

        $this->assertEquals('available', $result['status'], 'Un bon article doit être publié directement');
        $this->assertGreaterThanOrEqual(40, $result['friperie_score'], 'Score doit dépasser le seuil de visibilité');
        $this->assertEmpty($result['guardian_flags'], 'Aucun flag pour un bon article');
        $this->assertNotNull($result['published_at'], 'published_at doit être défini');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function article_avec_score_maximum_a_100_points(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Robe Zara fleurie taille M parfait état',
            'description'  => 'Belle robe Zara motif fleuri, taille M. Portée 2 fois, état impeccable. Couleur bleu marine. Convient pour occasions.',
            'price'        => 12000,
            'category'     => 'vetements',
            'condition'    => 'tres_bon_etat',
            'images_count' => 4,
        ], 'new');

        $this->assertEquals(100, $result['friperie_score']);
        $this->assertEquals('available', $result['status']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Détection contenu hors friperie
    // ─────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_iphone_dans_accessoires_est_bloque(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'iPhone 14 Pro Max 256Go',
            'description'  => 'Vente iPhone 14, parfait état avec chargeur',
            'price'        => 350000,
            'category'     => 'accessoires',
            'condition'    => 'tres_bon_etat',
            'images_count' => 2,
        ], 'new');

        $this->assertEquals('pending_review', $result['status'], 'iPhone doit être bloqué');
        $this->assertContains('prix_trop_eleve', $result['guardian_flags']);
        $this->assertTrue(
            collect($result['guardian_flags'])->contains(fn($f) => str_starts_with($f, 'mot_interdit:electronique')),
            'Flag électronique doit être levé'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_article_alimentaire_est_bloque(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Livraison thiébou djen Dakar',
            'description'  => 'Restaurant livraison thiéboudienne à domicile',
            'price'        => 3000,
            'category'     => 'vetements',
            'condition'    => 'bon_etat',
            'images_count' => 1,
        ], 'new');

        $this->assertEquals('pending_review', $result['status']);
        $this->assertTrue(
            collect($result['guardian_flags'])->contains(fn($f) => str_starts_with($f, 'mot_interdit:alimentation')),
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_service_est_bloque(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Cours particuliers maths terminale',
            'description'  => 'Prestation cours à domicile',
            'price'        => 5000,
            'category'     => 'accessoires',
            'condition'    => 'bon_etat',
            'images_count' => 0,
        ], 'new');

        $this->assertEquals('pending_review', $result['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prix_trop_eleve_bloque_larticle(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Veste Hermès vintage collection',
            'description'  => 'Veste Hermès authentique, très bon état',
            'price'        => 200000, // Au-dessus du plafond B2C 150k
            'category'     => 'vetements',
            'condition'    => 'tres_bon_etat',
            'images_count' => 3,
        ], 'new');

        $this->assertEquals('pending_review', $result['status']);
        $this->assertContains('prix_trop_eleve', $result['guardian_flags']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prix_trop_bas_leve_un_flag(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Chaussette noire',
            'description'  => 'Chaussette occasion',
            'price'        => 10, // En dessous du minimum 200 FCFA
            'category'     => 'vetements',
            'condition'    => 'bon_etat',
            'images_count' => 1,
        ], 'new');

        $this->assertContains('prix_trop_bas', $result['guardian_flags']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Score qualité
    // ─────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_article_bacle_a_un_score_faible(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'vêtement',  // titre générique
            'description'  => '',           // pas de description
            'price'        => 500,
            'category'     => 'vetements',
            'condition'    => '',
            'images_count' => 0,            // pas de photo
        ], 'new');

        $this->assertLessThan(40, $result['friperie_score'], 'Article bâclé doit avoir score < seuil');
        $this->assertEquals('available', $result['status'], 'Pas bloqué mais invisible dans le feed');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plus_de_photos_augmente_le_score(): void
    {
        $base = [
            'title'       => 'Jeans Levi 501 bleu délavé',
            'description' => 'Jeans en très bon état',
            'price'       => 8000,
            'category'    => 'vetements',
            'condition'   => 'tres_bon_etat',
        ];

        $sans_photo  = $this->guardian->analyze(array_merge($base, ['images_count' => 0]), 'new');
        $trois_photos = $this->guardian->analyze(array_merge($base, ['images_count' => 3]), 'new');

        $this->assertGreaterThan(
            $sans_photo['friperie_score'],
            $trois_photos['friperie_score'],
            '3 photos doit donner un meilleur score que 0 photo'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Trust level
    // ─────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_vendeur_de_confiance_publie_directement(): void
    {
        // Même article qui serait normalement analysé passe directement si trusted
        $result = $this->guardian->analyze([
            'title'        => 'Veste ordinateur portable',  // contient "ordinateur" mais vendeur trusted
            'description'  => 'Veste légère pour travail',
            'price'        => 10000,
            'category'     => 'vetements',
            'condition'    => 'bon_etat',
            'images_count' => 2,
        ], 'trusted');

        // Un vendeur trusted bypass la détection de mots — on lui fait confiance
        $this->assertEquals('available', $result['status'], 'Vendeur trusted → publié directement');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_vendeur_suspect_est_toujours_en_review(): void
    {
        $result = $this->guardian->analyze([
            'title'        => 'Robe traditionnelle wax magnifique',
            'description'  => 'Belle robe wax portée une seule fois pour une occasion',
            'price'        => 8000,
            'category'     => 'traditionnel',
            'condition'    => 'tres_bon_etat',
            'images_count' => 4,
        ], 'flagged');

        $this->assertEquals('pending_review', $result['status'], 'Vendeur flagged → toujours en review');
        $this->assertContains('vendeur_suspect', $result['guardian_flags']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Messages vendeur
    // ─────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_message_vendeur_est_bienveillant_et_explicatif(): void
    {
        $message = $this->guardian->getVendorMessage(['mot_interdit:electronique:iphone', 'prix_trop_eleve']);

        $this->assertStringContainsString('friperie', strtolower($message));
        $this->assertNotEmpty($message);
        $this->assertGreaterThan(20, strlen($message), 'Le message doit être suffisamment explicatif');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_message_est_positif_sans_flag(): void
    {
        $message = $this->guardian->getVendorMessage([]);

        $this->assertStringContainsString('ligne', $message);  // "en ligne"
    }
}

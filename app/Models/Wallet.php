<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'user_id',
        'credits',
        'seed_bonus_claimed',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'credits'            => 'integer',
        'seed_bonus_claimed' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Le portefeuille appartient à un utilisateur.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Historique complet des mouvements de crédits.
     */
    public function transactions()
    {
        return $this->hasMany(CreditTransaction::class)->orderByDesc('created_at');
    }

    // ─── Opérations Financières ──────────────────────────────────────────────

    /**
     * Créditer le portefeuille (rechargement, bonus, admin).
     *
     * @param  int    $amount   Nombre de jetons à ajouter
     * @param  string $reason   Motif (ex: 'seed_bonus', 'manual_topup')
     * @param  string|null $reference  Référence externe optionnelle
     * @param  string|null $note       Note admin optionnelle
     * @return CreditTransaction
     */
    public function addCredits(int $amount, string $reason, ?string $reference = null, ?string $note = null): CreditTransaction
    {
        $this->increment('credits', $amount);
        $this->refresh();

        return $this->transactions()->create([
            'type'          => 'credit',
            'amount'        => $amount,
            'balance_after' => $this->credits,
            'reason'        => $reason,
            'reference'     => $reference,
            'note'          => $note,
        ]);
    }

    /**
     * Débiter le portefeuille (achat de boost, etc.).
     *
     * @param  int    $amount   Nombre de jetons à retirer
     * @param  string $reason   Motif (ex: 'boost_coup_de_pioche', 'boost_story')
     * @param  string|null $reference  Référence externe (ex: article_id)
     * @return CreditTransaction
     *
     * @throws \RuntimeException Si le solde est insuffisant
     */
    public function spendCredits(int $amount, string $reason, ?string $reference = null): CreditTransaction
    {
        if ($this->credits < $amount) {
            throw new \RuntimeException(
                "Solde insuffisant. Vous avez {$this->credits} jetons, mais cette action en nécessite {$amount}."
            );
        }

        $this->decrement('credits', $amount);
        $this->refresh();

        return $this->transactions()->create([
            'type'          => 'debit',
            'amount'        => $amount,
            'balance_after' => $this->credits,
            'reason'        => $reason,
            'reference'     => $reference,
        ]);
    }

    /**
     * Vérifie si le portefeuille contient assez de crédits.
     */
    public function hasEnough(int $amount): bool
    {
        return $this->credits >= $amount;
    }

    /**
     * Attribuer la bourse de bienvenue gamifiée (50 jetons).
     * Ne peut être appelée qu'une seule fois par portefeuille.
     *
     * @return CreditTransaction|null  null si déjà attribuée
     */
    public function claimSeedBonus(): ?CreditTransaction
    {
        if ($this->seed_bonus_claimed) {
            return null;
        }

        $this->update(['seed_bonus_claimed' => true]);

        return $this->addCredits(
            50,
            'seed_bonus',
            null,
            'Bourse de bienvenue : profil complété à 100%'
        );
    }
}

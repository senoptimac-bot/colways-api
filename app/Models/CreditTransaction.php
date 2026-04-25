<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    use HasFactory;

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'reason',
        'reference',
        'note',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'amount'        => 'integer',
        'balance_after' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * La transaction appartient à un portefeuille.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si c'est un crédit (rechargement).
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Vérifie si c'est un débit (dépense).
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Libellé humain du motif de la transaction.
     */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'seed_bonus'            => 'Bourse de bienvenue',
            'manual_topup'          => 'Rechargement manuel (Wave)',
            'boost_coup_de_pioche'  => 'Coup de Pioche (Bump Feed)',
            'boost_story'           => 'Placement Story VIP',
            'detourage_premium'     => 'Détourage Premium (Photo principale) - 25 jetons',
            'admin_gift'            => 'Cadeau administrateur',
            'refund'                => 'Remboursement',
            default                 => $this->reason,
        };
    }
}

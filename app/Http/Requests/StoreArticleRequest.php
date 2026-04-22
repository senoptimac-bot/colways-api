<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    /**
     * Le Gardien du Cloud — Vérifie deux choses avant tout :
     *   1. Le vendeur est connecté ET possède un étal.
     *   2. Le vendeur Standard n'a pas dépassé son quota de publications journalières.
     *
     * Quotas selon le niveau (Phase Seed) :
     *   standard  → 10 articles / jour
     *   discovery → 20 articles / jour
     *   elite     → Illimité ♾️
     */
    public function authorize(): bool
    {
        $user = auth()->user();

        // Vérification de base : connecté + étal existant
        if (!$user || !$user->shop) {
            return false;
        }

        $shop = $user->shop;

        // Les Élites n'ont aucune limite — passage direct
        if ($shop->isElite()) {
            return true;
        }

        // Quota journalier selon le tier
        $dailyLimit = match ($shop->account_tier ?? 'standard') {
            'discovery' => 20,
            default     => 10, // standard
        };

        // Compte les articles créés par ce shop aujourd'hui
        $countToday = \App\Models\Article::where('shop_id', $shop->id)
            ->whereDate('created_at', today())
            ->count();

        if ($countToday >= $dailyLimit) {
            // On stocke le message d'erreur pour le récupérer depuis le Controller
            $this->merge(['__quota_exceeded' => true]);
            return false;
        }

        return true;
    }

    /**
     * Message personnalisé quand le quota est dépassé (retourné par l'API).
     */
    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        $shop = auth()->user()?->shop;
        $isQuotaExceeded = $this->input('__quota_exceeded', false);

        if ($isQuotaExceeded || $shop) {
            $tier = $shop?->account_tier ?? 'standard';
            $limit = $tier === 'discovery' ? 20 : 10;

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => "🔥 Votre stock brûle ! Limite de {$limit} publications/jour atteinte.",
                    'hint'    => "Passez au Cercle Élite pour publier en illimité ♾️",
                    'code'    => 'QUOTA_EXCEEDED',
                ], 403)
            );
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => 'Non autorisé.'], 403)
        );
    }

    /**
     * Sanitisation XSS avant validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title'               => strip_tags(trim($this->title ?? '')),
            'description'         => $this->description ? strip_tags(trim($this->description)) : null,
            'defects_description' => $this->defects_description ? strip_tags(trim($this->defects_description)) : null,
            'brand'               => $this->brand ? strip_tags(trim($this->brand)) : null,
            'sub_type'            => $this->sub_type ? strip_tags(trim($this->sub_type)) : null,
            'material'            => $this->material ? strip_tags(trim($this->material)) : null,
        ]);
    }

    /**
     * Règles de validation pour la publication d'un article.
     */
    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Prix en FCFA — entier positif, pas de centimes
            'price'       => ['required', 'integer', 'min:100', 'max:10000000'],

            // 6 catégories exclusives Colways
            'category'    => ['required', 'in:vetements,chaussures,sacs,montres,casquettes,accessoires'],

            // ─── État / Fraîcheur — Sprint 11 ─────────────────────────────────
            // Nouvelles valeurs + backward compat (neuf)
            'condition'   => ['required', 'in:arrivage_neuf,premier_choix,tres_bon_etat,bon_etat,neuf'],

            // ─── Taille — Sprint 10 + 11 ─────────────────────────────────────
            'size'        => ['nullable', 'string', 'max:20'],
            'color'       => ['nullable', 'string', 'max:30'],
            'size_fit'    => ['nullable', 'in:normal,petit,grand,oversize,slim'],

            // ─── Détecteur de défauts — Sprint 11 ────────────────────────────
            'defects_list'          => ['nullable', 'array'],
            'defects_list.*'        => ['string', 'in:tache,trou,bouton,decolor,fermeture,aucun'],
            'defects_description'   => ['nullable', 'string', 'max:500'],

            // ─── Origine & Négociation — Sprint 11 ───────────────────────────
            'origin_country'  => ['nullable', 'in:france,belgique,usa,uk,italie,dubai,autre'],
            'is_negotiable'   => ['sometimes', 'boolean'],

            // ─── Nouveaux champs Friperie — Sprint 12 ────────────────────────
            'gender'          => ['nullable', 'in:homme,femme,enfant,unisexe'],
            'brand'           => ['nullable', 'string', 'max:50'],
            'sub_type'        => ['nullable', 'string', 'max:50'],
            'material'        => ['nullable', 'string', 'max:50'],

            // ─── Champs palette B2B ───────────────────────────────────────────
            'poids_kg'         => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quantite_estimee' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'origine_pays'     => ['nullable', 'string', 'in:france,belgique,uk,usa,dubai,chine,maroc,autre'],
        ];
    }

    /**
     * Messages d'erreur en français.
     */
    public function messages(): array
    {
        return [
            'title.required'          => "Le titre de l'article est obligatoire.",
            'title.min'               => 'Le titre doit faire au moins 3 caractères.',
            'title.max'               => 'Le titre ne peut pas dépasser 200 caractères.',
            'price.required'          => 'Le prix est obligatoire.',
            'price.integer'           => 'Le prix doit être un nombre entier (en FCFA).',
            'price.min'               => 'Le prix minimum est 100 FCFA.',
            'category.required'       => 'La catégorie est obligatoire.',
            'category.in'             => 'Catégorie invalide.',
            'condition.required'      => "L'état de l'article est obligatoire.",
            'condition.in'            => 'État invalide. Choisis parmi : Arrivage Neuf, Premier Choix, Très bon état, Bon état.',
            'size_fit.in'             => 'Coupe invalide.',
            'defects_list.*.in'       => 'Défaut invalide.',
            'defects_description.max' => 'La description du défaut ne peut pas dépasser 500 caractères.',
            'origin_country.in'       => "Pays d'origine invalide.",
        ];
    }
}

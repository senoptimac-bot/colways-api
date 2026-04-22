<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    /**
     * Seul le propriétaire de l'article peut le modifier.
     * La vérification se fait aussi côté Policy — double sécurité.
     */
    public function authorize(): bool
    {
        $article = $this->route('article');
        return $article && $article->user_id === $this->user()->id;
    }

    /**
     * Sanitisation XSS avant validation.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        if ($this->has('title'))               $clean['title']               = strip_tags(trim($this->title));
        if ($this->has('description'))         $clean['description']         = $this->description ? strip_tags(trim($this->description)) : null;
        if ($this->has('defects_description')) $clean['defects_description'] = $this->defects_description ? strip_tags(trim($this->defects_description)) : null;
        if (!empty($clean)) $this->merge($clean);
    }

    /**
     * Règles de validation pour la modification d'un article (PATCH partiel).
     * Tous les champs sont optionnels.
     */
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price'       => ['sometimes', 'integer', 'min:100', 'max:10000000'],
            'category'    => ['sometimes', 'in:vetements,chaussures,sacs,montres,casquettes,accessoires'],
            'condition'   => ['sometimes', 'in:arrivage_neuf,premier_choix,tres_bon_etat,bon_etat,neuf'],

            // Taille
            'size'     => ['nullable', 'string', 'max:20'],
            'color'    => ['nullable', 'string', 'max:30'],
            'size_fit' => ['nullable', 'in:normal,petit,grand,oversize,slim'],

            // Défauts
            'defects_list'        => ['nullable', 'array'],
            'defects_list.*'      => ['string', 'in:tache,trou,bouton,decolor,fermeture,aucun'],
            'defects_description' => ['nullable', 'string', 'max:500'],

            // Origine & Négociation
            'origin_country' => ['nullable', 'in:france,belgique,usa,uk,italie,dubai,autre'],
            'is_negotiable'  => ['sometimes', 'boolean'],

            // Palette B2B
            'poids_kg'         => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quantite_estimee' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'origine_pays'     => ['nullable', 'string', 'in:france,belgique,uk,usa,dubai,chine,maroc,autre'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min'              => 'Le titre doit faire au moins 3 caractères.',
            'title.max'              => 'Le titre ne peut pas dépasser 200 caractères.',
            'price.integer'          => 'Le prix doit être un nombre entier (en FCFA).',
            'price.min'              => 'Le prix minimum est 100 FCFA.',
            'category.in'            => 'Catégorie invalide.',
            'condition.in'           => 'État invalide.',
            'size_fit.in'            => 'Coupe invalide.',
            'defects_list.*.in'      => 'Défaut invalide.',
            'origin_country.in'      => "Pays d'origine invalide.",
            'poids_kg.integer'       => 'Le poids doit être en kg entier.',
            'origine_pays.in'        => "Pays d'origine invalide.",
        ];
    }
}

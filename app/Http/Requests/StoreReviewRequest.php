<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Seul un utilisateur connecté peut laisser un avis.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Sanitisation XSS — strip les balises HTML du commentaire.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('comment') && $this->comment) {
            $this->merge([
                'comment' => strip_tags(trim($this->comment)),
            ]);
        }
    }

    /**
     * Règles de validation pour un avis vendeur.
     */
    public function rules(): array
    {
        return [
            'shop_id'    => ['required', 'integer', 'exists:shops,id'],
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_id.required' => 'L\'étal à évaluer est obligatoire.',
            'shop_id.exists'   => 'Cet étal n\'existe pas.',
            'rating.required'  => 'La note est obligatoire.',
            'rating.min'       => 'La note minimum est 1 étoile.',
            'rating.max'       => 'La note maximum est 5 étoiles.',
            'comment.max'      => 'Le commentaire ne peut pas dépasser 500 caractères.',
        ];
    }
}

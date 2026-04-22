<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    /**
     * Tout le monde peut signaler — même sans compte.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour un signalement.
     */
    public function rules(): array
    {
        return [
            'article_id'  => ['required', 'integer', 'exists:articles,id'],
            'reason'      => ['required', 'in:arnaque,article_inexistant,contenu_inapproprie,autre'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Messages d'erreur en français.
     */
    public function messages(): array
    {
        return [
            'article_id.required' => 'L\'article à signaler est obligatoire.',
            'article_id.exists'   => 'Cet article n\'existe pas.',
            'reason.required'     => 'La raison du signalement est obligatoire.',
            'reason.in'           => 'Raison invalide.',
            'description.max'     => 'La description ne peut pas dépasser 500 caractères.',
        ];
    }
}

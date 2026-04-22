<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
{
    /**
     * Seul un utilisateur connecté peut créer un étal.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Sanitisation XSS avant validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shop_name'   => strip_tags(trim($this->shop_name ?? '')),
            'description' => $this->description ? strip_tags(trim($this->description)) : null,
        ]);
    }

    /**
     * Règles de validation pour la création d'un étal.
     */
    public function rules(): array
    {
        return [
            // Nom de l'étal — affiché dans le feed et sur la page étal
            'shop_name'   => ['required', 'string', 'min:2', 'max:150'],

            // Description optionnelle de l'étal
            'description' => ['nullable', 'string', 'max:1000'],

            // Quartier historique (non utilisé mais maintenu nullable)
            'quartier'    => ['nullable', 'string'],

            // Nouvelle adresse formatée (Géolocalisation reverse)
            'address'     => ['nullable', 'string', 'max:255'],

            // Type d'étal — Sprint 8 B2B
            'type'        => ['sometimes', 'in:particulier,grossiste'],

            // Géolocalisation — Sprint Maps
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Messages d'erreur en français.
     */
    public function messages(): array
    {
        return [
            'shop_name.required'  => 'Le nom de ton étal est obligatoire.',
            'shop_name.min'       => 'Le nom de l\'étal doit faire au moins 2 caractères.',
            'shop_name.max'       => 'Le nom de l\'étal ne peut pas dépasser 150 caractères.',
            'description.max'    => 'La description ne peut pas dépasser 1000 caractères.',
            'description.max'    => 'La description ne peut pas dépasser 1000 caractères.',
            'quartier.in'        => 'Quartier invalide.',
        ];
    }
}

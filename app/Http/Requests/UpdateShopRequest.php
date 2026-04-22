<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
{
    /**
     * Seul le propriétaire de l'étal peut le modifier.
     */
    public function authorize(): bool
    {
        $shop = $this->route('shop');
        return $shop && $shop->user_id === $this->user()->id;
    }

    /**
     * Sanitisation XSS — strip les balises HTML des champs texte libres.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];

        if ($this->has('shop_name')) {
            $clean['shop_name'] = strip_tags(trim($this->shop_name));
        }
        if ($this->has('description')) {
            $clean['description'] = $this->description ? strip_tags(trim($this->description)) : null;
        }
        if ($this->has('specialties')) {
            $clean['specialties'] = $this->specialties ? strip_tags(trim($this->specialties)) : null;
        }

        if (!empty($clean)) {
            $this->merge($clean);
        }
    }

    /**
     * Règles de validation pour la modification d'un étal.
     * Champs optionnels (PATCH partiel).
     */
    public function rules(): array
    {
        return [
            'shop_name'   => ['sometimes', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'specialties' => ['nullable', 'string', 'max:200'],
            'quartier'    => ['nullable', 'string'],
            'address'     => ['nullable', 'string', 'max:255'],
            'type'        => ['sometimes', 'in:particulier,grossiste'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_name.min'    => "Le nom de l'étal doit faire au moins 3 caractères.",
            'shop_name.max'    => "Le nom de l'étal ne peut pas dépasser 150 caractères.",
            'description.max'  => 'La description ne peut pas dépasser 1000 caractères.',
            'specialties.max'  => 'Les spécialités ne peuvent pas dépasser 200 caractères.',
            'quartier.in'      => 'Quartier invalide.',
            'type.in'          => "Type d'étal invalide. Valeurs : particulier, grossiste.",
        ];
    }
}

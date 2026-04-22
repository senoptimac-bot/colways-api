<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Seul l'utilisateur connecté peut modifier son profil.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Règles de validation pour la mise à jour du profil.
     */
    public function rules(): array
    {
        return [
            'name'            => ['sometimes', 'required', 'string', 'max:100'],
            'whatsapp_number' => [
                'sometimes', 
                'required', 
                'string', 
                'regex:/^\+221[37][05678][0-9]{7}$/',
                Rule::unique('users', 'whatsapp_number')->ignore(auth()->id()),
            ],
            'password'        => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Messages personnalisés.
     */
    public function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'Le numéro WhatsApp doit être au format +221XXXXXXXXX.',
            'whatsapp_number.unique' => 'Ce numéro WhatsApp est déjà utilisé.',
            'password.min'         => 'Le mot de passe doit faire au moins 8 caractères.',
            'password.confirmed'   => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}

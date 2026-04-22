<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Tout utilisateur peut s'inscrire — pas de vérification d'autorisation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour l'inscription.
     *
     * Numéro WhatsApp sénégalais : +221 suivi de 9 chiffres.
     * Format accepté : +221XXXXXXXXX (ex: +221771234567)
     * Opérateurs sénégalais : Orange (77), Free (76), Expresso (70), Wave...
     */
    public function rules(): array
    {
        return [
            // Nom complet du vendeur / acheteur
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            // Numéro de téléphone — identifiant unique de connexion sur Colways
            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone',
            ],

            // Numéro WhatsApp sénégalais — validé à l'inscription
            // Format obligatoire : +221 suivi de exactement 9 chiffres
            // Ex valides : +221771234567, +221701234567, +221761234567
            'whatsapp_number' => [
                'required',
                'string',
                'regex:/^\+221[0-9]{9}$/',
            ],

            // Mot de passe — minimum 8 caractères, confirmé
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    /**
     * Messages d'erreur personnalisés en français.
     * Respecte le guide copywriting Colways — messages directs et compréhensibles.
     */
    public function messages(): array
    {
        return [
            'name.required'              => 'Ton prénom est obligatoire.',
            'name.min'                   => 'Ton prénom doit faire au moins 2 caractères.',
            'name.max'                   => 'Ton prénom ne peut pas dépasser 100 caractères.',

            'phone.required'             => 'Ton numéro de téléphone est obligatoire.',
            'phone.unique'               => 'Ce numéro est déjà utilisé sur Colways.',

            'whatsapp_number.required'   => 'Ton numéro WhatsApp est obligatoire.',
            'whatsapp_number.regex'      => 'Format invalide. Utilise le format international : +221771234567',

            'password.required'          => 'Un mot de passe est obligatoire.',
            'password.min'               => 'Ton mot de passe doit faire au moins 8 caractères.',
            'password.confirmed'         => 'Les deux mots de passe ne correspondent pas.',
        ];
    }

    /**
     * Attributs lisibles dans les messages d'erreur.
     */
    public function attributes(): array
    {
        return [
            'name'             => 'prénom',
            'phone'            => 'numéro de téléphone',
            'whatsapp_number'  => 'numéro WhatsApp',
            'password'         => 'mot de passe',
        ];
    }
}

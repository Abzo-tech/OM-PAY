<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'currency' => ['required', 'string', 'size:3', Rule::in(['XOF', 'EUR', 'USD'])],
            'description' => 'nullable|string|max:255',
            'recipient_account' => 'required|string|exists:accounts,account_number',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant minimum est de 0.01.',
            'amount.max' => 'Le montant maximum est de 1,000,000.',
            'currency.required' => 'La devise est obligatoire.',
            'currency.size' => 'La devise doit contenir exactement 3 caractères.',
            'currency.in' => 'La devise doit être XOF, EUR ou USD.',
            'description.max' => 'La description ne peut pas dépasser 255 caractères.',
            'recipient_account.required' => 'Le numéro de compte destinataire est obligatoire.',
            'recipient_account.exists' => 'Le compte destinataire n\'existe pas.',
        ];
    }
}

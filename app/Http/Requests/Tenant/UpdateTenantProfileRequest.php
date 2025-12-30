<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateTenantProfileRequest - Business Profile Validation
 *
 * Validates name, phone, address, social links, and branding images.
 */
final class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del negocio',
            'description' => 'descripción',
            'about' => 'historia',
            'phone' => 'teléfono',
            'whatsapp_number' => 'WhatsApp',
            'address' => 'dirección',
            'logo' => 'logo',
            'cover' => 'portada',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del negocio es obligatorio.',
            'logo.max' => 'El logo no puede superar 1MB.',
            'cover.max' => 'La portada no puede superar 2MB.',
            '*.url' => 'La URL debe ser válida (ej: https://...).',
        ];
    }
}

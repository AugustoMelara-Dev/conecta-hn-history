<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateScheduleRequest - Strict Schedule Validation
 */
final class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $rules = [
            'schedule' => ['required', 'array'],
        ];

        foreach ($days as $day) {
            $rules["schedule.{$day}"] = ['array'];
            $rules["schedule.{$day}.isOpen"] = ['required', 'boolean'];
            $rules["schedule.{$day}.open"] = ['nullable', 'date_format:H:i', 'required_if:schedule.{$day}.isOpen,true'];
            $rules["schedule.{$day}.close"] = ['nullable', 'date_format:H:i', 'required_if:schedule.{$day}.isOpen,true', 'after:schedule.{$day}.open'];
        }

        return $rules;
    }
}

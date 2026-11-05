<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvolutionGoWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => 'nullable|string|max:120',
            'instance' => 'nullable|string|max:160',
            'instanceName' => 'nullable|string|max:160',
            'data' => 'nullable',
        ];
    }
}

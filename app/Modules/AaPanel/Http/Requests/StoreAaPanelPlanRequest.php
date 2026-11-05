<?php

namespace App\Modules\AaPanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAaPanelPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'server_id' => ['nullable', 'exists:aapanel_servers,id'],
            'disk_mb' => ['required', 'integer', 'min:100'],
            'bandwidth_mb' => ['required', 'integer', 'min:100'],
            'database_limit' => ['required', 'integer', 'min:0'],
            'ftp_limit' => ['required', 'integer', 'min:0'],
            'domain_limit' => ['required', 'integer', 'min:1'],
            'php_version' => ['required', 'string', 'max:10'],
            'ssl_enabled' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}

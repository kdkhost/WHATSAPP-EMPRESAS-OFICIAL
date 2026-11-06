<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'tenant_id','name','email','document','birth_date','cpf','cnpj',
        'person_type','phone','whatsapp','whatsapp_optin','last_interaction_at',
        'cep','logradouro','numero','complemento','bairro','cidade','uf',
        'meta','active',
    ];

    protected $casts = [
        'meta'                => 'array',
        'active'              => 'boolean',
        'birth_date'          => 'date:Y-m-d',
        'whatsapp_optin'      => 'boolean',
        'last_interaction_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_whatsapp',
        'formatted_phone',
        'formatted_document',
        'formatted_cep',
        'formatted_birth_date',
        'formatted_cpf',
        'formatted_cnpj',
        'whatsapp_optin_label',
    ];

    public function getFormattedWhatsappAttribute(): string
    {
        return \App\Support\Format::phone($this->whatsapp);
    }

    public function getFormattedPhoneAttribute(): string
    {
        return \App\Support\Format::phone($this->phone);
    }

    public function getFormattedDocumentAttribute(): string
    {
        return \App\Support\Format::document($this->document);
    }

    public function getFormattedCepAttribute(): string
    {
        return \App\Support\Format::cep($this->cep);
    }

    public function getBirthDateAttribute($value)
    {
        $val = $value ?: ($this->meta['birth_date'] ?? null);
        if (!$val) {
            return null;
        }
        if ($val instanceof \DateTimeInterface) {
            return $val;
        }
        try {
            return \Carbon\Carbon::parse($val);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getFormattedBirthDateAttribute(): string
    {
        $birth = $this->birth_date ?: ($this->meta['birth_date'] ?? null);
        if (!$birth) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($birth)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $birth;
        }
    }

    public function getCpfAttribute($value)
    {
        if ($value) {
            return $value;
        }
        if (!empty($this->meta['cpf'])) {
            return $this->meta['cpf'];
        }
        $doc = preg_replace('/\D/', '', (string) $this->document);
        return strlen($doc) === 11 ? $doc : null;
    }

    public function getFormattedCpfAttribute(): string
    {
        return \App\Support\Format::cpf($this->cpf);
    }

    public function getCnpjAttribute($value)
    {
        if ($value) {
            return $value;
        }
        if (!empty($this->meta['cnpj'])) {
            return $this->meta['cnpj'];
        }
        $doc = preg_replace('/\D/', '', (string) $this->document);
        return strlen($doc) === 14 ? $doc : null;
    }

    public function getFormattedCnpjAttribute(): string
    {
        return \App\Support\Format::cnpj($this->cnpj);
    }

    public function getWhatsappOptinLabelAttribute(): string
    {
        $optin = $this->attributes['whatsapp_optin'] ?? ($this->meta['whatsapp_optin'] ?? true);
        return $optin ? 'Sim' : 'Não';
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class);
    }

    public function installments()
    {
        return $this->hasMany(\App\Modules\Tenancy\Models\CustomerPackageInstallment::class);
    }
}

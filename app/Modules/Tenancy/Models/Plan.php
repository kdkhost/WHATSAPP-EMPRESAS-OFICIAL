<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_cycle', 'trial_days',
        'extra_instance_price',
        'max_instances', 'max_customers', 'max_invoices_month', 'max_messages_month',
        'max_contracts_month',
        'custom_gateway', 'custom_templates', 'installment_enabled', 'fee_passthrough',
        'features', 'active', 'sort', 'is_demo', 'highlight', 'badge',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'extra_instance_price' => 'decimal:2',
        'custom_gateway'      => 'boolean',
        'custom_templates'    => 'boolean',
        'installment_enabled' => 'boolean',
        'fee_passthrough'     => 'boolean',
        'features'            => 'array',
        'active'              => 'boolean',
        'is_demo'             => 'boolean',
        'highlight'           => 'boolean',
    ];

    public function tenants() { return $this->hasMany(Tenant::class); }
}

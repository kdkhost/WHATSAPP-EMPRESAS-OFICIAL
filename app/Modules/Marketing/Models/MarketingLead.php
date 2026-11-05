<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    protected $table = 'marketing_leads';

    protected $guarded = ['id'];

    public function getCleanWhatsappAttribute(): string
    {
        return preg_replace('/\D/', '', (string) $this->whatsapp);
    }

    public function getWhatsappUrlAttribute(): string
    {
        $num = $this->clean_whatsapp;
        if (! str_starts_with($num, '55') && strlen($num) >= 10 && strlen($num) <= 11) {
            $num = '55' . $num;
        }
        $name = urlencode($this->name ?: 'Cliente');
        $msg = urlencode("Olá {$this->name}! Vi que você se cadastrou em nossa página promocional. Como posso te ajudar?");
        return "https://wa.me/{$num}?text={$msg}";
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'contacted' => 'Em Contato',
            'converted' => 'Convertido',
            'lost'      => 'Perdido',
            default     => 'Novo Lead',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'contacted' => 'info',
            'converted' => 'success',
            'lost'      => 'secondary',
            default     => 'warning',
        };
    }
}

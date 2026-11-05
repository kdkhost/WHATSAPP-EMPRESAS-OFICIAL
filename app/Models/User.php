<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'document', 'phone', 'avatar_url',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
        'lgpd_accepted', 'lgpd_accepted_at', 'theme_dark', 'tour_completed_at', 'notification_sound_enabled',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled', 'two_factor_confirmed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'lgpd_accepted'    => 'boolean',
            'lgpd_accepted_at' => 'datetime',
            'theme_dark'       => 'boolean',
            'tour_completed_at' => 'datetime',
            'notification_sound_enabled' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getRoleAttribute(): string
    {
        return $this->attributes['role'] ?? 'client';
    }
}

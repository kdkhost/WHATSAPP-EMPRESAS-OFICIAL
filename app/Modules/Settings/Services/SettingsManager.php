<?php

namespace App\Modules\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acesso unificado a configuracoes (chave -> valor) gerenciadas pelo painel.
 * Cache em memoria + cache permanente.
 */
class SettingsManager
{
    protected array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        [$group, $name] = $this->split($key);

        $all = Cache::rememberForever('settings.all', function () {
            try {
                return DB::table('settings')->get()
                    ->mapWithKeys(fn ($r) => [$r->group.'.'.$r->key => ['value' => $r->value, 'type' => $r->type]])
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });

        $row = $all[$group.'.'.$name] ?? $this->legacyRow($all, $group, $name);
        $value = $row ? $this->cast($row['value'], $row['type']) : $default;
        return $this->cache[$key] = $value;
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        [$group, $name] = $this->split($key);
        $stored = match ($type) {
            'json'  => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool'  => $value ? '1' : '0',
            default => (string) $value,
        };
        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'key' => $name],
            ['value' => $stored, 'type' => $type, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->deleteLegacyKey($group, $name);

        Cache::forget('settings.all');
        unset($this->cache[$key]);
    }

    public function group(string $group): array
    {
        try {
            $current = DB::table('settings')->where('group', $group)->get()
                ->mapWithKeys(fn ($r) => [$r->key => $this->cast($r->value, $r->type)])
                ->all();

            $legacy = $this->legacyGroup($group);

            return array_merge($legacy, $current);
        } catch (\Throwable) {
            return [];
        }
    }

    public function forget(string $key): void
    {
        [$group, $name] = $this->split($key);

        DB::table('settings')->where('group', $group)->where('key', $name)->delete();
        $this->deleteLegacyKey($group, $name);

        Cache::forget('settings.all');
        unset($this->cache[$key]);
    }

    public function forgetGroup(string $group): void
    {
        DB::table('settings')->where('group', $group)->delete();

        [$legacyGroup, $legacyPrefix] = $this->legacyCoordinates($group, null);
        if ($legacyGroup && $legacyPrefix) {
            DB::table('settings')
                ->where('group', $legacyGroup)
                ->where('key', 'like', $legacyPrefix.'.%')
                ->delete();
        }

        Cache::forget('settings.all');
        $this->cache = [];
    }

    protected function split(string $key): array
    {
        if (! str_contains($key, '.')) {
            return ['app', $key];
        }

        $pos = strrpos($key, '.');

        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }

    protected function cast(?string $v, string $type): mixed
    {
        return match ($type) {
            'json'  => $v ? json_decode($v, true) : null,
            'bool'  => $v === '1',
            'int'   => (int) $v,
            'float' => (float) $v,
            default => $v,
        };
    }

    protected function legacyRow(array $all, string $group, string $name): ?array
    {
        [$legacyGroup, $legacyPrefix] = $this->legacyCoordinates($group, $name);

        if (! $legacyGroup || ! $legacyPrefix) {
            return null;
        }

        return $all[$legacyGroup.'.'.$legacyPrefix] ?? null;
    }

    protected function legacyGroup(string $group): array
    {
        [$legacyGroup, $legacyPrefix] = $this->legacyCoordinates($group, null);

        if (! $legacyGroup || ! $legacyPrefix) {
            return [];
        }

        return DB::table('settings')
            ->where('group', $legacyGroup)
            ->where('key', 'like', $legacyPrefix.'.%')
            ->get()
            ->mapWithKeys(function ($row) use ($legacyPrefix) {
                $key = substr((string) $row->key, strlen($legacyPrefix) + 1);

                return [$key => $this->cast($row->value, $row->type)];
            })
            ->all();
    }

    protected function legacyCoordinates(string $group, ?string $name): array
    {
        if (! str_contains($group, '.')) {
            return [null, null];
        }

        $parts = explode('.', $group);
        $legacyGroup = array_shift($parts);
        $prefix = implode('.', $parts);

        if ($name !== null) {
            $prefix .= '.'.$name;
        }

        return [$legacyGroup, $prefix];
    }

    protected function deleteLegacyKey(string $group, string $name): void
    {
        [$legacyGroup, $legacyKey] = $this->legacyCoordinates($group, $name);

        if (! $legacyGroup || ! $legacyKey) {
            return;
        }

        DB::table('settings')
            ->where('group', $legacyGroup)
            ->where('key', $legacyKey)
            ->delete();
    }
}

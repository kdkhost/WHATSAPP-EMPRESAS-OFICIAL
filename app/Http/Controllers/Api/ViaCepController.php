<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ViaCepController extends Controller
{
    public function lookup(string $cep): JsonResponse
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return response()->json(['error' => 'cep_invalido'], 422);
        }

        $payload = Cache::remember("viacep:{$cep}", now()->addDays(30), function () use ($cep) {
            $base = rtrim((string) env('VIACEP_BASE_URL', 'https://viacep.com.br/ws'), '/');
            $resp = Http::timeout(8)->get("{$base}/{$cep}/json/");
            if (! $resp->ok()) {
                return null;
            }
            $data = $resp->json();
            if (! is_array($data) || ! empty($data['erro'])) {
                return null;
            }
            return [
                'cep'         => $data['cep'] ?? '',
                'logradouro'  => $data['logradouro'] ?? '',
                'complemento' => $data['complemento'] ?? '',
                'bairro'      => $data['bairro'] ?? '',
                'cidade'      => $data['localidade'] ?? '',
                'uf'          => $data['uf'] ?? '',
                'ibge'        => $data['ibge'] ?? '',
            ];
        });

        if (! $payload) {
            return response()->json(['error' => 'nao_encontrado'], 404);
        }

        return response()->json($payload);
    }
}

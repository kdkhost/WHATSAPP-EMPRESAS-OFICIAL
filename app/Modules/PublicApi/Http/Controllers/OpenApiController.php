<?php

namespace App\Modules\PublicApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Spec OpenAPI 3.0.3 da API publica.
 * Servida em /api/openapi.json e consumida pela Swagger UI em /api/docs.
 */
class OpenApiController extends Controller
{
    public function spec(Request $request, SettingsManager $settings): JsonResponse
    {
        $base = rtrim((string) (config('app.url') ?: $request->getSchemeAndHttpHost()), '/');
        $appName = (string) $settings->get('app.name', config('app.name'));
        $logoUrl = $this->publicAssetUrl($settings->get('app.logo') ?: $settings->get('app.favicon'));
        $supportEmail = (string) (
            $settings->get('app.email_support')
            ?: config('mail.from.address')
            ?: 'suporte@cobrancawhatsapp.com'
        );

        $info = [
            'title'       => $appName . ' - API Publica',
            'description' => 'API REST publica do '.$appName.' para integracoes externas com Zapier, N8N, Make e Power Automate. Autenticacao via Bearer Token (Sanctum).',
            'version'     => '1.0.0',
            'contact'     => ['name' => 'Suporte '.$appName, 'email' => $supportEmail],
        ];

        if ($logoUrl) {
            $info['x-logo'] = [
                'url' => $logoUrl,
                'altText' => $appName,
            ];
        }

        return response()->json([
            'openapi' => '3.0.3',
            'info' => $info,
            'servers' => [
                ['url' => $base . '/api/v1', 'description' => 'Producao'],
            ],
            'security' => [['BearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'API Token (Sanctum)',
                    ],
                ],
                'schemas' => [
                    'Customer' => [
                        'type' => 'object',
                        'properties' => [
                            'id'        => ['type' => 'integer', 'example' => 1],
                            'name'      => ['type' => 'string', 'example' => 'Joao da Silva'],
                            'email'     => ['type' => 'string', 'example' => 'joao@email.com'],
                            'document'  => ['type' => 'string', 'example' => '12345678900'],
                            'whatsapp'  => ['type' => 'string', 'example' => '5511999998888'],
                        ],
                    ],
                    'Invoice' => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'integer', 'example' => 1],
                            'number'      => ['type' => 'string', 'example' => 'INV-001'],
                            'customer_id' => ['type' => 'integer', 'example' => 1],
                            'amount'      => ['type' => 'number', 'format' => 'float', 'example' => 199.90],
                            'description' => ['type' => 'string', 'example' => 'Mensalidade Janeiro'],
                            'due_date'    => ['type' => 'string', 'format' => 'date', 'example' => '2026-01-15'],
                            'status'      => ['type' => 'string', 'enum' => ['pending', 'sent', 'paid', 'overdue', 'canceled']],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors'  => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/customers' => [
                    'get' => [
                        'summary' => 'Listar clientes',
                        'tags'    => ['Customers'],
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Pagina'],
                            ['name' => 'q',    'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'Busca por nome/email'],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Lista paginada', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Customer']]]]],
                            '401' => ['description' => 'Token invalido', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Criar cliente',
                        'tags'    => ['Customers'],
                        'requestBody' => [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Customer']]],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Cliente criado', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Customer']]]],
                            '422' => ['description' => 'Validacao falhou', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        ],
                    ],
                ],
                '/invoices' => [
                    'get' => [
                        'summary' => 'Listar faturas',
                        'tags'    => ['Invoices'],
                        'parameters' => [
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['pending', 'sent', 'paid', 'overdue', 'canceled']]],
                            ['name' => 'page',   'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Lista paginada', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Invoice']]]]],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Criar fatura',
                        'tags'    => ['Invoices'],
                        'requestBody' => [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Invoice']]],
                        ],
                        'responses' => ['201' => ['description' => 'Fatura criada']],
                    ],
                ],
                '/invoices/{id}/send' => [
                    'post' => [
                        'summary' => 'Enviar cobranca via WhatsApp',
                        'tags'    => ['Invoices'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Cobranca enviada'],
                            '422' => ['description' => 'Fatura ja paga ou cancelada'],
                        ],
                    ],
                ],
                '/dispatches' => [
                    'get' => [
                        'summary' => 'Listar envios agendados',
                        'tags'    => ['Dispatches'],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/templates' => [
                    'get' => [
                        'summary' => 'Listar templates de cobranca',
                        'tags'    => ['Templates'],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/stats' => [
                    'get' => [
                        'summary' => 'Estatisticas gerais',
                        'tags'    => ['Stats'],
                        'responses' => [
                            '200' => [
                                'description' => 'KPIs do tenant',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'customers' => ['type' => 'integer'],
                                        'invoices'  => ['type' => 'integer'],
                                        'revenue'   => ['type' => 'number'],
                                    ],
                                ]]],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => [
                ['name' => 'Customers',  'description' => 'Gestao de clientes'],
                ['name' => 'Invoices',   'description' => 'Gestao de faturas'],
                ['name' => 'Dispatches', 'description' => 'Envios de cobranca'],
                ['name' => 'Templates',  'description' => 'Templates de mensagem'],
                ['name' => 'Stats',      'description' => 'Estatisticas'],
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function publicAssetUrl(mixed $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        return url($path);
    }
}

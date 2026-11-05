<?php

namespace App\Modules\CustomerImport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerImport\Services\CsvImporter;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    public function __construct(protected CsvImporter $importer) {}

    /** Tenant_id ativo (nunca do request). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index()
    {
        return view('customer-import::index');
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:10240|mimes:csv,txt']);
        try {
            $data = $this->importer->preview($request->file('file'));
            return response()->json(['ok' => true, ...$data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => 'required|file|max:10240|mimes:csv,txt',
            'mapping' => 'required|array',
        ]);

        // tenant_id SEMPRE do contexto autenticado — nunca do request body
        $tenantId = $this->activeTenantId();

        try {
            $stats = $this->importer->import(
                $request->file('file'),
                $request->input('mapping'),
                $tenantId,
            );
            return response()->json(['ok' => true, 'stats' => $stats]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

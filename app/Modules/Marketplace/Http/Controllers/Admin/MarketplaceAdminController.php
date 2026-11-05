<?php

namespace App\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\AddonSubmission;
use App\Modules\Marketplace\Services\AddonMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MarketplaceAdminController extends Controller
{
    public function __construct(protected AddonMarketplaceService $marketplace) {}

    public function index()
    {
        $catalog = $this->marketplace->adminCatalog();
        $submissions = $this->marketplace->recentSubmissions();

        return view('marketplace::admin.index', compact('catalog', 'submissions'));
    }

    public function approve(int $id): JsonResponse
    {
        $submission = AddonSubmission::query()->findOrFail($id);
        $addon = $this->marketplace->approve($submission, (int) Auth::guard('admin')->id());

        return response()->json([
            'ok' => true,
            'message' => 'Addon aprovado, instalado e publicado no marketplace.',
            'addon' => $addon,
        ]);
    }

    public function reject(int $id): JsonResponse
    {
        $submission = AddonSubmission::query()->findOrFail($id);
        $reason = (string) request('reason', '');
        $this->marketplace->reject($submission, (int) Auth::guard('admin')->id(), $reason);

        return response()->json([
            'ok' => true,
            'message' => 'Submissao rejeitada e devolvida ao desenvolvedor.',
        ]);
    }
}

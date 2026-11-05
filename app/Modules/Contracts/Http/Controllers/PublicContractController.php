<?php

namespace App\Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicContractController extends Controller
{
    public function __construct(protected ContractService $service) {}

    /** Pagina publica para o cliente revisar/assinar. */
    public function show(string $token, Request $request)
    {
        $contract = Contract::with(['customer', 'tenant', 'template', 'invoice'])->where('access_token', $token)->firstOrFail();

        if ($contract->isSigned()) {
            return redirect()->route('public.contracts.signed', $token);
        }
        if ($contract->isExpired()) {
            return response()->view('contracts::public.expired', compact('contract'), 410);
        }

        // Marca como visualizado
        if ($contract->status === 'sent') {
            $contract->update(['status' => 'viewed', 'viewed_at' => now()]);
            $contract->recordEvent('viewed', $request->ip(), $request->userAgent());
        }

        return response()->view('contracts::public.show', compact('contract'))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')->header('Pragma', 'no-cache');
    }

    /** Recebe a assinatura. */
    public function sign(string $token, Request $request): JsonResponse
    {
        $contract = Contract::where('access_token', $token)->firstOrFail();

        if ($contract->isExpired()) {
            return response()->json(['ok' => false, 'error' => 'Contrato expirado.'], 410);
        }
        if ($contract->isSigned()) {
            return response()->json(['ok' => false, 'error' => 'Contrato já assinado.'], 422);
        }

        $data = $request->validate([
            'signer_name'     => 'required|string|max:120',
            'signer_email'    => 'required|email',
            'signer_document' => 'nullable|string|max:30',
            'signature_image' => 'required|string',  // data:image/png;base64,...
            'return_channel'  => 'required|in:email,whatsapp,both',
            'accept_terms'    => 'required|accepted',
        ]);

        $data['ip'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $this->service->sign($contract, $data);

        return response()->json([
            'ok' => true,
            'redirect' => route('public.contracts.signed', $token),
        ]);
    }

    /** Pagina de sucesso/visualizacao do contrato assinado. */
    public function signed(string $token)
    {
        $contract = Contract::where('access_token', $token)->where('status', 'signed')->firstOrFail();
        $contract->loadMissing('customer', 'tenant', 'invoice');
        return view('contracts::public.signed', compact('contract'));
    }

    /** Download do contrato assinado (HTML/PDF). */
    public function download(string $token)
    {
        $contract = Contract::where('access_token', $token)->where('status', 'signed')->firstOrFail();

        if ($contract->signed_pdf_path && Storage::disk('local')->exists($contract->signed_pdf_path)) {
            $content = Storage::disk('local')->get($contract->signed_pdf_path);
            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"contrato-{$contract->number}.pdf\"",
            ]);
        }

        $htmlPath = $contract->signed_html_path;
        if (! $htmlPath || ! Storage::disk('local')->exists($htmlPath)) {
            $this->service->generateAndSendCopy($contract->loadMissing('customer', 'tenant', 'invoice'));
            $contract->refresh();
            $htmlPath = $contract->signed_html_path;
        }

        if (! $htmlPath || ! Storage::disk('local')->exists($htmlPath)) {
            return view('contracts::public.signed', compact('contract'));
        }

        $content = Storage::disk('local')->get($htmlPath);

        return response($content, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"contrato-{$contract->number}.html\"",
        ]);
    }
}

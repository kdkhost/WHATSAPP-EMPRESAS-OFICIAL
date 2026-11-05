<?php

namespace App\Modules\InvoicePdf\Services;

use App\Models\Invoice;
use App\Support\Format;

/**
 * Geracao de PDF de fatura usando HTML + impressao do navegador.
 * Estrategia: gera HTML printavel (CSS @media print) que pode ser
 * baixado como PDF pelo navegador (window.print() -> Salvar como PDF).
 *
 * Para PDF real server-side, instale dompdf/dompdf via composer:
 *   composer require dompdf/dompdf
 * E use o metodo renderWithDompdf() abaixo.
 */
class InvoicePdfService
{
    public function renderHtml(Invoice $invoice): string
    {
        $invoice->loadMissing('customer');
        return view('invoice-pdf::invoice', compact('invoice'))->render();
    }

    /**
     * Se dompdf estiver instalado, gera PDF binario.
     * Fallback: retorna HTML printavel.
     */
    public function generatePdfBinary(Invoice $invoice): string|false
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return false;
        }

        $html = $this->renderHtml($invoice);
        $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}

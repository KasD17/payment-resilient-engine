<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfGenerator
{
    /**
     * @param  array{
     *     invoiceNumber: string,
     *     date: string,
     *     status: string,
     *     customerName: string,
     *     customerEmail: string,
     *     gateway: string,
     *     transactionId: ?string,
     *     description: string,
     *     formattedSubtotal: string,
     *     formattedTax: string,
     *     formattedTotal: string
     * }  $data
     */
    public function generate(array $data): string
    {
        $pdf = Pdf::loadView('invoices.template', $data);

        $filename = 'invoices/'.$data['invoiceNumber'].'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}

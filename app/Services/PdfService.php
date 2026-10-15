<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PdfService
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function quotation(array $quotation, array $items, array $customer, array $case): string
    {
        return $this->write('quotation', [
            'document' => $quotation,
            'items' => $items,
            'customer' => $customer,
            'case' => $case,
            'company' => $this->company(),
            'kind' => 'quotation',
        ], 'quotations', $quotation['number'] . '.pdf');
    }

    public function invoice(array $invoice, array $items, array $customer, array $case): string
    {
        return $this->write('invoice', [
            'document' => $invoice,
            'items' => $items,
            'customer' => $customer,
            'case' => $case,
            'company' => $this->company(),
            'kind' => 'invoice',
        ], 'invoices', $invoice['number'] . '.pdf');
    }

    public function receipt(array $payment, array $invoice, array $customer): string
    {
        return $this->write('receipt', [
            'payment' => $payment,
            'invoice' => $invoice,
            'customer' => $customer,
            'company' => $this->company(),
        ], 'receipts', $payment['number'] . '.pdf');
    }

    private function write(string $view, array $data, string $folder, string $filename): string
    {
        $html = View::render('pdf/' . $view, $data);
        $directory = storage_path($folder);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $target = $directory . '/' . $filename;
        $temp = storage_path('cache/mpdf');
        if (!is_dir($temp)) {
            mkdir($temp, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $temp,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_left' => 12,
            'margin_right' => 12,
        ]);
        $mpdf->SetTitle($filename);
        $mpdf->SetFooter('AtteCert|{PAGENO} / {nbpg}|' . date('Y'));
        $mpdf->WriteHTML($html);
        $mpdf->Output($target, Destination::FILE);

        return $target;
    }

    private function company(): array
    {
        return [
            'name' => (string) $this->settings->get('company_name', 'AtteCert'),
            'email' => (string) $this->settings->get('email', ''),
            'phone' => (string) $this->settings->get('phone', ''),
            'address' => (string) $this->settings->get('address', ''),
            'registration' => (string) $this->settings->get('registration', ''),
            'logo' => base_path('public/assets/brand/logo-mark.png'),
        ];
    }
}

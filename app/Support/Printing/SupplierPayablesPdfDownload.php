<?php

namespace App\Support\Printing;

use Carbon\Carbon;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Illuminate\Http\Response;

class SupplierPayablesPdfDownload
{
    private const INK = [0.09, 0.16, 0.25];
    private const MUTED = [0.36, 0.42, 0.50];
    private const RULE = [0.82, 0.86, 0.90];
    private const HEADERS = ['Supplier', 'Invoice', 'Purchase Date', 'Total', 'Paid', 'Balance Due', 'Due Date', 'Last Payment'];
    private const WIDTHS = [165, 110, 80, 85, 85, 85, 75, 95];

    public static function make(
        array $branding,
        iterable $purchases,
        string $search,
        float $outstandingAmount,
        int $invoiceCount,
        int $supplierCount
    ): Response {
        $pdf = new Dompdf();
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml('<html><body></body></html>');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $bold = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $y = self::drawHeader($canvas, $branding, $search, $outstandingAmount, $invoiceCount, $supplierCount, $font, $bold, true);

        foreach ($purchases as $purchase) {
            $fields = [
                (string) ($purchase->supplier?->name ?? 'N/A'),
                (string) ($purchase->invoice_number ?? 'N/A'),
                $purchase->purchase_date?->format('d M Y') ?? 'N/A',
                number_format((float) $purchase->total_amount, 2),
                number_format((float) $purchase->amount_paid, 2),
                number_format((float) $purchase->balance_due, 2),
                $purchase->due_date?->format('d M Y') ?? 'N/A',
                $purchase->supplier_payments_max_payment_date
                    ? Carbon::parse($purchase->supplier_payments_max_payment_date)->format('d M Y')
                    : 'None',
            ];

            $wrapped = [];
            foreach ($fields as $index => $value) {
                $wrapped[] = self::wrap($canvas, $value, $font, 7.5, self::WIDTHS[$index] - 6);
            }

            $lines = max(array_map('count', $wrapped));
            $height = max(18, $lines * 9 + 6);
            if ($y + $height > 555) {
                $canvas->new_page();
                $y = self::drawHeader($canvas, $branding, $search, $outstandingAmount, $invoiceCount, $supplierCount, $font, $bold, false);
            }

            $x = 30;
            foreach ($wrapped as $index => $cellLines) {
                foreach ($cellLines as $lineIndex => $line) {
                    $left = $index >= 3 && $index <= 5
                        ? $x + self::WIDTHS[$index] - 2 - $canvas->get_text_width($line, $font, 7.5)
                        : $x + 2;
                    $canvas->text($left, $y + 3 + $lineIndex * 9, $line, $font, 7.5, self::INK);
                }
                $x += self::WIDTHS[$index];
            }
            $y += $height;
            $canvas->line(30, $y, 810, $y, self::RULE, 0.3);
        }

        if ($invoiceCount === 0) {
            $canvas->text(32, $y + 10, 'No outstanding supplier invoices found.', $font, 9, self::MUTED);
        }
        $canvas->page_text(730, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, self::MUTED);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="supplier-payables-' . now()->format('Ymd-His') . '.pdf"',
        ]);
    }

    private static function drawHeader(
        Canvas $canvas,
        array $branding,
        string $search,
        float $outstandingAmount,
        int $invoiceCount,
        int $supplierCount,
        string $font,
        string $bold,
        bool $firstPage
    ): float {
        $y = 18;
        if ($firstPage && ($branding['show_logo'] ?? false)) {
            $logoFile = self::logoFile($branding);
            $dimensions = $logoFile ? @getimagesize($logoFile) : false;
            if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
                $scale = min(55 / $dimensions[0], 53 / $dimensions[1]);
                $width = $dimensions[0] * $scale;
                $height = $dimensions[1] * $scale;
                $canvas->image($logoFile, (841.89 - $width) / 2, $y, $width, $height);
                $y += $height + 4;
            }
        }

        $y = self::center($canvas, (string) ($branding['company_name'] ?? 'KIM Rx'), $bold, $firstPage ? 13 : 11, $y);
        if ($firstPage && !empty($branding['branch_name'])) {
            $y = self::center($canvas, 'Branch: ' . $branding['branch_name'], $font, 8, $y);
        }
        $y += 2;
        $y = self::center($canvas, 'Supplier Payables', $bold, $firstPage ? 11 : 10, $y);
        if ($search !== '') {
            $y = self::center($canvas, 'Search: ' . $search, $font, 8, $y);
        }
        $y += 3;
        $y = self::center($canvas, sprintf(
            'Outstanding: UGX %s     Open Invoices: %s     Suppliers Owed: %s',
            number_format($outstandingAmount, 2),
            number_format($invoiceCount),
            number_format($supplierCount)
        ), $bold, 8, $y);
        $y += 5;
        $canvas->line(30, $y, 810, $y, self::RULE, 0.7);
        $y += 5;
        $canvas->filled_rectangle(30, $y, 780, 17, [0.96, 0.97, 0.98]);

        $x = 30;
        foreach (self::HEADERS as $index => $label) {
            $canvas->text($x + 2, $y + 4, $label, $bold, 7, self::MUTED);
            $x += self::WIDTHS[$index];
        }
        $y += 17;
        $canvas->line(30, $y, 810, $y, self::RULE, 0.7);

        return $y + 2;
    }

    private static function logoFile(array $branding): ?string
    {
        $file = $branding['logo_file'] ?? null;
        if (!$file || !is_file($file)) {
            return null;
        }

        $dimensions = @getimagesize($file);
        if (!$dimensions) {
            return null;
        }

        $isVip = stripos((string) ($branding['company_name'] ?? ''), 'VIP PHARMACY') !== false;
        $needsFallback = ($dimensions[2] === IMAGETYPE_PNG && !extension_loaded('gd'))
            || max($dimensions[0], $dimensions[1]) > 3000;
        if ($isVip && $needsFallback) {
            $fallback = public_path('images/vip-sidebar-logo.jpg');

            return is_file($fallback) ? $fallback : null;
        }

        if ($dimensions[2] === IMAGETYPE_PNG && !extension_loaded('gd')) {
            return null;
        }

        return in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) ? $file : null;
    }

    private static function center(Canvas $canvas, string $text, string $font, float $size, float $y): float
    {
        foreach (self::wrap($canvas, $text, $font, $size, 770) as $line) {
            $width = $canvas->get_text_width($line, $font, $size);
            $canvas->text((841.89 - $width) / 2, $y, $line, $font, $size, self::INK);
            $y += $size + 3;
        }

        return $y;
    }

    private static function wrap(Canvas $canvas, string $text, string $font, float $size, float $width): array
    {
        $lines = [''];
        foreach (mb_str_split(preg_replace('/\s+/u', ' ', trim($text))) as $character) {
            $last = count($lines) - 1;
            if ($lines[$last] !== '' && $canvas->get_text_width($lines[$last] . $character, $font, $size) > $width) {
                $lines[] = '';
                $last++;
            }
            $lines[$last] .= $character;
        }

        return array_map('trim', $lines);
    }
}

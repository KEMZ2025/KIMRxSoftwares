<?php

namespace App\Support\Printing;

use Carbon\Carbon;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Illuminate\Http\Response;

class SalesPerformancePdfDownload
{
    private const INK = [0.09, 0.16, 0.25];
    private const MUTED = [0.36, 0.42, 0.50];
    private const RULE = [0.82, 0.86, 0.90];

    public static function make(array $data): Response
    {
        $pdf = new Dompdf();
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml('<html><body></body></html>');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $bold = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $y = self::drawHeader($canvas, $data, $font, $bold);

        foreach ($data['profitDetailRows'] as $row) {
            $fields = [
                $row['sale_date'] ? Carbon::parse($row['sale_date'])->format('d M Y') : 'N/A',
                (string) $row['invoice_number'],
                (string) $row['receipt_number'],
                (string) $row['sale_type_label'],
                (string) $row['dispenser_name'],
                (string) $row['customer_name'],
                (string) $row['product_name'],
                (string) $row['batch_number'],
            ];
            $widths = [62, 84, 84, 64, 112, 160, 150, 64];
            $wrapped = [];
            foreach ($fields as $index => $value) {
                $wrapped[] = self::wrap($canvas, $value, $font, 7, $widths[$index] - 6);
            }

            $lines = max(array_map('count', $wrapped));
            $height = max(23, $lines * 9 + 14);
            if ($y + $height > 552) {
                $canvas->new_page();
                $y = self::drawHeader($canvas, $data, $font, $bold);
            }

            $x = 30;
            foreach ($wrapped as $index => $cellLines) {
                foreach ($cellLines as $lineIndex => $line) {
                    $canvas->text($x + 2, $y + 2 + $lineIndex * 9, $line, $font, 7, self::INK);
                }
                $x += $widths[$index];
            }

            $numbers = [
                number_format((float) $row['quantity'], 2),
                number_format((float) $row['purchase_price'], 2),
                number_format((float) $row['unit_price'], 2),
                number_format((float) $row['total_amount'], 2),
                number_format((float) $row['cost_amount'], 2),
                number_format((float) $row['gross_profit'], 2),
                number_format((float) $row['margin'], 1) . '%',
            ];
            $numberWidths = [65, 110, 110, 110, 110, 110, 80];
            $x = 30;
            foreach ($numbers as $index => $number) {
                $canvas->text($x + 2, $y + 2 + $lines * 9, $number, $font, 7, self::INK);
                $x += $numberWidths[$index];
            }
            $y += $height;
            $canvas->line(30, $y, 810, $y, self::RULE, 0.3);
        }

        $canvas->page_text(730, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, self::MUTED);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="sales-performance-' . now()->format('Ymd-His') . '.pdf"',
        ]);
    }

    private static function drawHeader(Canvas $canvas, array $data, string $font, string $bold): float
    {
        $branding = $data['branding'];
        $filters = $data['filters'];
        $totals = $data['profitDetailTotals'];
        $canvas->text(30, 24, (string) $branding['company_name'], $bold, 13, self::INK);
        $canvas->text(30, 43, (string) ($branding['branch_name'] ?? ''), $font, 8, self::MUTED);
        $canvas->text(30, 60, 'Sales Performance Report', $bold, 12, self::INK);
        $canvas->text(30, 79, (string) $data['rangeLabel'], $font, 9, self::INK);

        $channel = $data['profitSaleTypeOptions'][$filters['profit_sale_type']] ?? 'All Sales Channels';
        $dispenser = $data['profitDispenserOptions']->firstWhere('id', (int) $filters['profit_dispenser_id'])?->name ?? 'All Dispensers';
        $customer = $data['profitCustomerOptions']->firstWhere('id', (int) $filters['profit_customer_id'])?->name ?? 'All Customers';
        $canvas->text(30, 94, "Channel: $channel | Dispenser: $dispenser", $font, 8, self::MUTED);
        $canvas->text(30, 107, "Customer: $customer | Receipt / Invoice: " . ($filters['profit_document_search'] ?: 'All'), $font, 8, self::MUTED);
        $canvas->text(30, 124, sprintf(
            'Net Sales: UGX %s     Cost: UGX %s     Gross Profit: UGX %s     Margin: %.1f%%',
            number_format((float) $totals['revenue'], 2),
            number_format((float) $totals['cost'], 2),
            number_format((float) $totals['gross_profit'], 2),
            (float) $totals['margin']
        ), $bold, 8, self::INK);
        $canvas->line(30, 140, 810, 140, self::RULE, 0.7);

        $x = 30;
        foreach (['Date', 'Invoice', 'Receipt', 'Type', 'Dispenser', 'Customer', 'Product', 'Batch'] as $index => $label) {
            $canvas->text($x + 2, 147, $label, $bold, 7, self::MUTED);
            $x += [62, 84, 84, 64, 112, 160, 150, 64][$index];
        }
        $x = 30;
        foreach (['Qty', 'Unit Cost', 'Unit Selling', 'Sales', 'Cost Amount', 'Profit', 'Margin'] as $index => $label) {
            $canvas->text($x + 2, 160, $label, $bold, 7, self::MUTED);
            $x += [65, 110, 110, 110, 110, 110, 80][$index];
        }
        $canvas->line(30, 174, 810, 174, self::RULE, 0.7);

        return 179;
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

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
    private const HEADERS = ['Date', 'Invoice', 'Dispenser', 'Product', 'Qty', 'Cost', 'Selling', 'Sales', 'Profit', 'Margin'];
    private const WIDTHS = [60, 84, 96, 180, 40, 64, 64, 64, 64, 64];

    public static function make(array $data): Response
    {
        $pdf = new Dompdf();
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml('<html><body></body></html>');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $bold = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $y = self::drawHeader($canvas, $data, $font, $bold, true);

        foreach ($data['profitDetailRows'] as $row) {
            $fields = [
                $row['sale_date'] ? Carbon::parse($row['sale_date'])->format('d M Y') : 'N/A',
                (string) $row['invoice_number'],
                (string) $row['dispenser_name'],
                (string) $row['product_name'],
                number_format((float) $row['quantity'], 2),
                number_format((float) $row['purchase_price'], 2),
                number_format((float) $row['unit_price'], 2),
                number_format((float) $row['total_amount'], 2),
                number_format((float) $row['gross_profit'], 2),
                number_format((float) $row['margin'], 1) . '%',
            ];
            $wrapped = [];
            foreach ($fields as $index => $value) {
                $wrapped[] = self::wrap($canvas, $value, $font, 7.5, self::WIDTHS[$index] - 6);
            }

            $lines = max(array_map('count', $wrapped));
            $height = max(17, $lines * 9 + 6);
            if ($y + $height > 555) {
                $canvas->new_page();
                $y = self::drawHeader($canvas, $data, $font, $bold, false);
            }

            $x = 30;
            foreach ($wrapped as $index => $cellLines) {
                foreach ($cellLines as $lineIndex => $line) {
                    $left = $index < 4
                        ? $x + 2
                        : $x + self::WIDTHS[$index] - 2 - $canvas->get_text_width($line, $font, 7.5);
                    $canvas->text($left, $y + 3 + $lineIndex * 9, $line, $font, 7.5, self::INK);
                }
                $x += self::WIDTHS[$index];
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

    private static function drawHeader(Canvas $canvas, array $data, string $font, string $bold, bool $firstPage): float
    {
        $branding = $data['branding'];
        $filters = $data['filters'];
        $totals = $data['profitDetailTotals'];
        $y = 18;

        if ($firstPage && ($branding['show_logo'] ?? false)) {
            $logoFile = self::logoFile($branding);
            $dimensions = $logoFile ? @getimagesize($logoFile) : false;
            if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
                $scale = min(70 / $dimensions[0], 68 / $dimensions[1]);
                $width = $dimensions[0] * $scale;
                $height = $dimensions[1] * $scale;
                $canvas->image($logoFile, (841.89 - $width) / 2, $y, $width, $height);
                $y += $height + 4;
            }
        }

        $y = self::center($canvas, (string) $branding['company_name'], $bold, $firstPage ? 13 : 11, $y);
        if ($firstPage) {
            foreach ([$branding['receipt_header'] ?? null, $branding['company_address'] ?? null] as $line) {
                if ($line) {
                    $y = self::center($canvas, (string) $line, $font, 8, $y);
                }
            }
            $contacts = array_filter([$branding['company_phone'] ?? null, $branding['company_email'] ?? null]);
            if ($contacts) {
                $y = self::center($canvas, implode(' | ', $contacts), $font, 8, $y);
            }
            if (($branding['show_branch_contacts'] ?? false) && !empty($branding['branch_name'])) {
                $y = self::center($canvas, 'Branch: ' . $branding['branch_name'], $font, 8, $y);
            }
        }

        $y += 2;
        $y = self::center($canvas, 'Sales Performance Report', $bold, $firstPage ? 11 : 10, $y);
        $y = self::center($canvas, (string) $data['rangeLabel'], $font, 8, $y);

        $channel = $data['profitSaleTypeOptions'][$filters['profit_sale_type']] ?? 'All Sales Channels';
        $dispenser = $data['profitDispenserOptions']->firstWhere('id', (int) $filters['profit_dispenser_id'])?->name ?? 'All Dispensers';
        if ($firstPage) {
            $filterLine = "Channel: $channel | Dispenser: $dispenser";
            if ($filters['profit_customer_id']) {
                $customer = $data['profitCustomerOptions']->firstWhere('id', (int) $filters['profit_customer_id'])?->name;
                $filterLine .= ' | Customer: ' . ($customer ?: 'Selected');
            }
            if ($filters['profit_document_search']) {
                $filterLine .= ' | Receipt / Invoice: ' . $filters['profit_document_search'];
            }
            $y = self::center($canvas, $filterLine, $font, 7.5, $y);
        }

        $y += 3;
        $y = self::center($canvas, sprintf(
            'Net Sales: UGX %s     Cost: UGX %s     Gross Profit: UGX %s     Margin: %.1f%%',
            number_format((float) $totals['revenue'], 2),
            number_format((float) $totals['cost'], 2),
            number_format((float) $totals['gross_profit'], 2),
            (float) $totals['margin']
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

    private static function center(Canvas $canvas, string $text, string $font, float $size, float $y): float
    {
        foreach (self::wrap($canvas, $text, $font, $size, 770) as $line) {
            $width = $canvas->get_text_width($line, $font, $size);
            $canvas->text((841.89 - $width) / 2, $y, $line, $font, $size, self::INK);
            $y += $size + 3;
        }

        return $y;
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
        $needsFallback = $dimensions[2] === IMAGETYPE_PNG && !extension_loaded('gd');
        $needsFallback = $needsFallback || max($dimensions[0], $dimensions[1]) > 3000;
        if ($isVip && $needsFallback) {
            $fallback = public_path('images/vip-sidebar-logo.jpg');

            return is_file($fallback) ? $fallback : null;
        }

        if ($dimensions[2] === IMAGETYPE_PNG && !extension_loaded('gd')) {
            return null;
        }

        return in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) ? $file : null;
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

<?php

namespace App\Support\Printing;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Illuminate\Http\Response;

class StockReconciliationPdfDownload
{
    private const WIDTHS = [165, 80, 65, 60, 85, 50, 50, 60, 65, 100];
    private const HEADERS = ['Product', 'Batch', 'Unit cost', 'Opening qty', 'Opening value', 'In qty', 'Sold qty', 'Other out', 'Closing qty', 'Closing value'];

    public static function make(array $data): Response
    {
        // Draw rows directly, as in SalesPerformancePdfDownload, to keep large
        // monthly inventories out of Dompdf's memory-intensive HTML table layout.
        $pdf = new Dompdf();
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml('<html><body></body></html>');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $bold = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $y = self::header($canvas, $data, $font, $bold, true);
        foreach ($data['stockReconciliation']['rows'] as $row) {
            $fields = [$row['product'] . ($row['issues'] ? ' [Review: ' . $row['issues'] . ']' : ''), $row['batch']];
            foreach (['unit_cost', 'opening', 'opening_value', 'incoming', 'sold', 'other_out', 'closing', 'closing_value'] as $key) {
                $fields[] = number_format($row[$key], 2);
            }
            $wrapped = [];
            foreach ($fields as $index => $field) {
                $wrapped[] = self::wrap($canvas, (string) $field, $font, 7, self::WIDTHS[$index] - 6);
            }
            $height = max(18, max(array_map('count', $wrapped)) * 9 + 6);
            if ($y + $height > 550) {
                $canvas->new_page();
                $y = self::header($canvas, $data, $font, $bold, false);
            }
            $x = 30;
            foreach ($wrapped as $index => $lines) {
                foreach ($lines as $lineIndex => $line) {
                    $left = $index < 2 ? $x + 3 : $x + self::WIDTHS[$index] - 3 - $canvas->get_text_width($line, $font, 7);
                    $canvas->text($left, $y + 3 + $lineIndex * 9, $line, $font, 7);
                }
                $x += self::WIDTHS[$index];
            }
            $y += $height;
            $canvas->line(30, $y, 810, $y, [0.8, 0.83, 0.86], 0.3);
        }
        if ($data['stockReconciliation']['rows']->isEmpty()) {
            $canvas->text(33, $y + 8, 'No stock history found for this period.', $font, 9);
        }
        $canvas->page_text(690, 573, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7);
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="opening-closing-stock-' . $data['stockReconciliation']['from'] . '-' . $data['stockReconciliation']['to'] . '.pdf"',
        ]);
    }

    private static function header(Canvas $canvas, array $data, string $font, string $bold, bool $first): float
    {
        $report = $data['stockReconciliation'];
        $branding = $data['branding'];
        $y = 20;
        $logo = $branding['logo_file'] ?? null;
        if ($first && ($branding['show_logo'] ?? false) && $logo && is_file($logo)) {
            $size = @getimagesize($logo);
            if ($size && ($size[2] === IMAGETYPE_JPEG || ($size[2] === IMAGETYPE_PNG && extension_loaded('gd')))) {
                $scale = min(50 / $size[0], 50 / $size[1]);
                $canvas->image($logo, 30, $y, $size[0] * $scale, $size[1] * $scale);
                $y += 54;
            }
        }
        $lines = [
            (string) $branding['company_name'] . ' | ' . ($branding['branch_name'] ?? ''),
            'Opening & Closing Stock | ' . $report['from'] . ' to ' . $report['to'],
            'Opening value: UGX ' . number_format($report['totals']['opening_value'], 2) . '     Closing value: UGX ' . number_format($report['totals']['closing_value'], 2),
        ];
        foreach ($lines as $line) {
            foreach (self::wrap($canvas, $line, $bold, 10, 780) as $part) {
                $canvas->text(30, $y, $part, $bold, 10);
                $y += 14;
            }
        }
        $notes = [
            $report['basis'],
            'Opening imports through: ' . ($report['import_cutoff'] ?? 'before start date') . '. Batches: ' . $report['totals']['batch_count'] . '. Review: ' . $report['totals']['review_count'] . '. Unlinked records/groups excluded: ' . $report['unlinked'] . '.',
        ];
        if ($report['totals']['review_count'] || $report['unlinked']) {
            $notes[] = 'PROVISIONAL VALUES - resolve stock history and cost exceptions before relying on these totals.';
        }
        foreach ($notes as $note) {
            foreach (self::wrap($canvas, $note, $font, 7, 780) as $part) {
                $canvas->text(30, $y, $part, $font, 7);
                $y += 10;
            }
        }
        $y += 5;
        $canvas->filled_rectangle(30, $y, 780, 20, [0.94, 0.96, 0.98]);
        $x = 30;
        foreach (self::HEADERS as $index => $label) {
            $canvas->text($x + 3, $y + 6, $label, $bold, 6.5);
            $x += self::WIDTHS[$index];
        }
        return $y + 21;
    }

    private static function wrap(Canvas $canvas, string $text, string $font, float $size, float $width): array
    {
        $lines = [''];
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $index = count($lines) - 1;
            $candidate = ltrim($lines[$index] . ' ' . $word);
            if ($canvas->get_text_width($candidate, $font, $size) <= $width) {
                $lines[$index] = $candidate;
                continue;
            }
            if ($lines[$index] !== '') {
                $lines[] = '';
                $index++;
            }
            foreach (mb_str_split($word) as $character) {
                if ($lines[$index] !== '' && $canvas->get_text_width($lines[$index] . $character, $font, $size) > $width) {
                    $lines[] = '';
                    $index++;
                }
                $lines[$index] .= $character;
            }
        }
        return array_map('trim', $lines);
    }
}

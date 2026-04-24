<?php

namespace App\Support\Printing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfDownload
{
    public static function make(
        string $fileName,
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'portrait'
    ): Response {
        $payload = self::normalizePayload($data);

        return Pdf::loadView($view, $payload)
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 120,
            ])
            ->setPaper($paper, $orientation)
            ->download($fileName);
    }

    private static function normalizePayload(array $data): array
    {
        $data['autoPrint'] = false;
        $data['isPdfDownload'] = true;

        if (isset($data['branding']) && is_array($data['branding'])) {
            $data['branding'] = self::normalizeBranding($data['branding']);
        }

        return $data;
    }

    private static function normalizeBranding(array $branding): array
    {
        if (! self::supportsPdfImages()) {
            $branding['show_logo'] = false;
            $branding['logo_url'] = null;
            $branding['logo_file'] = null;

            return $branding;
        }

        if (!empty($branding['logo_file'])) {
            $branding['logo_url'] = $branding['logo_file'];
        }

        return $branding;
    }

    private static function supportsPdfImages(): bool
    {
        return extension_loaded('gd');
    }
}

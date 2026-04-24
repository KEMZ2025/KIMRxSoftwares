<?php

namespace App\Support\Printing;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownload
{
    public static function make(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            if ($headers !== []) {
                fputcsv($handle, $headers);
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

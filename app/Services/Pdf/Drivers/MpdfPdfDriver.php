<?php

namespace App\Services\Pdf\Drivers;

use App\Services\Pdf\Contracts\PdfDriverInterface;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class MpdfPdfDriver implements PdfDriverInterface
{
    public function supportsPdf(): bool
    {
        return true;
    }

    public function generate(string $html, string $relativeBasePath): array
    {
        $htmlRelativePath = "{$relativeBasePath}.html";
        $pdfRelativePath = "{$relativeBasePath}.pdf";
        $htmlAbsolutePath = storage_path('app/public/'.$htmlRelativePath);
        $pdfAbsolutePath = storage_path('app/public/'.$pdfRelativePath);
        $tempDirectory = (string) config('jobhunter.pdf.mpdf_temp_dir', storage_path('app/tmp/mpdf'));
        if ($tempDirectory === '') {
            $tempDirectory = storage_path('app/tmp/mpdf');
        }

        File::ensureDirectoryExists(dirname($htmlAbsolutePath));
        File::ensureDirectoryExists(dirname($pdfAbsolutePath));
        File::ensureDirectoryExists($tempDirectory);
        File::put($htmlAbsolutePath, $html);

        $pdf = new Mpdf([
            'tempDir' => $tempDirectory,
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font_size' => 10,
            'default_font' => 'dejavusans',
        ]);

        $pdf->SetTitle('Tailored Resume');
        $pdf->WriteHTML($html);
        $pdf->Output($pdfAbsolutePath, Destination::FILE);

        return [
            'html_path' => $htmlRelativePath,
            'pdf_path' => $pdfRelativePath,
        ];
    }
}

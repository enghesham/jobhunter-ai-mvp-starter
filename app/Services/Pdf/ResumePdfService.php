<?php

namespace App\Services\Pdf;

use App\Services\Pdf\Contracts\PdfDriverInterface;
use App\Services\Pdf\Drivers\BrowsershotPdfDriver;
use App\Services\Pdf\Drivers\HtmlOnlyPdfDriver;

class ResumePdfService
{
    public function generate(string $html, string $relativeBasePath): array
    {
        return $this->driver()->generate($html, $relativeBasePath);
    }

    private function driver(): PdfDriverInterface
    {
        return match ((string) config('jobhunter.pdf_driver', 'html')) {
            'browsershot', 'playwright' => app(BrowsershotPdfDriver::class),
            default => app(HtmlOnlyPdfDriver::class),
        };
    }
}

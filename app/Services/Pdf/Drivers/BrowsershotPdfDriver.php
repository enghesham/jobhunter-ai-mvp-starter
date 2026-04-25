<?php

namespace App\Services\Pdf\Drivers;

use App\Services\Pdf\Contracts\PdfDriverInterface;
use RuntimeException;

class BrowsershotPdfDriver implements PdfDriverInterface
{
    public function generate(string $html, string $relativeBasePath): array
    {
        throw new RuntimeException('The browsershot/playwright PDF driver is not enabled in this MVP build.');
    }
}

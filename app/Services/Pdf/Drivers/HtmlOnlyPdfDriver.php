<?php

namespace App\Services\Pdf\Drivers;

use App\Services\Pdf\Contracts\PdfDriverInterface;
use Illuminate\Support\Facades\File;

class HtmlOnlyPdfDriver implements PdfDriverInterface
{
    public function supportsPdf(): bool
    {
        return false;
    }

    public function generate(string $html, string $relativeBasePath): array
    {
        $htmlRelativePath = "{$relativeBasePath}.html";
        $absolutePath = storage_path('app/public/'.$htmlRelativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $html);

        return [
            'html_path' => $htmlRelativePath,
            'pdf_path' => null,
        ];
    }
}

<?php

namespace App\Services\Pdf\Contracts;

interface PdfDriverInterface
{
    public function supportsPdf(): bool;

    /**
     * @return array{html_path: string, pdf_path: string|null}
     */
    public function generate(string $html, string $relativeBasePath): array;
}

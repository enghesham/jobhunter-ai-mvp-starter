<?php

namespace App\Services\Pdf\Contracts;

interface PdfDriverInterface
{
    /**
     * @return array{html_path: string, pdf_path: string|null}
     */
    public function generate(string $html, string $relativeBasePath): array;
}

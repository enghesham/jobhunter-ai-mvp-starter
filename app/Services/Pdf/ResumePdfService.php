<?php

namespace App\Services\Pdf;

class ResumePdfService
{
    public function generate(string $html, string $targetPath): string
    {
        // TODO: Replace with Browsershot / Playwright implementation.
        file_put_contents($targetPath, $html);

        return $targetPath;
    }
}

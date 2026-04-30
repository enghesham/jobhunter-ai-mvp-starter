<?php

namespace App\Services\Pdf\Drivers;

use App\Services\Pdf\Contracts\PdfDriverInterface;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BrowsershotPdfDriver implements PdfDriverInterface
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

        File::ensureDirectoryExists(dirname($htmlAbsolutePath));
        File::put($htmlAbsolutePath, $html);

        $browser = $this->browserExecutable();
        $temporaryProfilePath = storage_path('app/tmp/pdf-browser-profile-'.md5($relativeBasePath));
        File::ensureDirectoryExists($temporaryProfilePath);

        $process = new Process([
            $browser,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--allow-file-access-from-files',
            '--user-data-dir='.$temporaryProfilePath,
            '--print-to-pdf='.$pdfAbsolutePath,
            '--no-pdf-header-footer',
            $this->fileUrl($htmlAbsolutePath),
        ]);

        $process->setTimeout((int) config('jobhunter.pdf.timeout', 60));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('Headless browser PDF generation failed: '.trim($process->getErrorOutput() ?: $exception->getMessage()));
        }

        if (! File::exists($pdfAbsolutePath)) {
            throw new RuntimeException('PDF generation did not create the expected output file.');
        }

        return [
            'html_path' => $htmlRelativePath,
            'pdf_path' => $pdfRelativePath,
        ];
    }

    private function browserExecutable(): string
    {
        $configuredPath = (string) config('jobhunter.pdf.browser_path');

        if ($configuredPath !== '' && File::exists($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No supported headless browser executable was found. Configure JOBHUNTER_PDF_BROWSER_PATH.');
    }

    private function fileUrl(string $path): string
    {
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        return 'file:///'.ltrim($normalizedPath, '/');
    }
}

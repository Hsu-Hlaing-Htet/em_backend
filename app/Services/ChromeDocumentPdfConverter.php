<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ChromeDocumentPdfConverter implements DocumentPdfConverter
{
    public function convert(string $html): string
    {
        $chromePath = $this->resolveChromePath();

        if ($chromePath === null) {
            throw new RuntimeException('Chrome executable is not configured or not executable for PDF conversion.');
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rosewood-pdf-'.uniqid('', true);
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create temporary directory for PDF conversion.');
        }

        $htmlPath = $directory.DIRECTORY_SEPARATOR.'document.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.'document.pdf';

        try {
            if (file_put_contents($htmlPath, $html) === false) {
                throw new RuntimeException('Unable to write temporary HTML for PDF conversion.');
            }

            $pdf = $this->shouldUseCdpPageNumbers($html)
                ? $this->convertWithCdp($chromePath, $htmlPath, $pdfPath, $html)
                : $this->convertWithCli($chromePath, $htmlPath, $pdfPath);

            if ($pdf === false || $pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('Chrome produced an invalid PDF file.');
            }

            return $pdf;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
            @rmdir($directory);
        }
    }

    private function shouldUseCdpPageNumbers(string $html): bool
    {
        return str_contains($html, 'pdf-sheet--list');
    }

    private function convertWithCdp(string $chromePath, string $htmlPath, string $pdfPath, string $html): string
    {
        $script = base_path('bin/chrome-print-pdf.mjs');
        $node = $this->resolveNodePath();

        if ($node === null || ! is_file($script)) {
            return $this->convertWithCli($chromePath, $htmlPath, $pdfPath);
        }

        $landscape = ! str_contains($html, 'A4 portrait');

        $result = Process::timeout(120)->run([
            $node,
            $script,
            $chromePath,
            $htmlPath,
            $pdfPath,
            $landscape ? '1' : '0',
        ]);

        if ($result->failed() || ! is_file($pdfPath)) {
            // Fall back to CLI conversion so exports still work if CDP is unavailable.
            return $this->convertWithCli($chromePath, $htmlPath, $pdfPath);
        }

        return (string) file_get_contents($pdfPath);
    }

    private function convertWithCli(string $chromePath, string $htmlPath, string $pdfPath): string
    {
        $chromeArgs = [
            $chromePath,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-translate',
            '--hide-scrollbars',
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            '--print-to-pdf='.$pdfPath,
            'file://'.$htmlPath,
        ];

        if ($this->shouldDisableSandbox()) {
            array_splice($chromeArgs, 1, 0, [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-setuid-sandbox',
            ]);
        }

        $result = Process::timeout(120)->run($chromeArgs);

        if ($result->failed()) {
            throw new RuntimeException('Chrome PDF conversion failed: '.$result->errorOutput());
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Chrome did not produce a PDF file.');
        }

        return (string) file_get_contents($pdfPath);
    }

    private function resolveChromePath(): ?string
    {
        $candidates = array_filter([
            (string) config('documents.chrome_path'),
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveNodePath(): ?string
    {
        $candidates = array_filter([
            (string) env('NODE_PATH_BIN', ''),
            trim((string) shell_exec('command -v node 2>/dev/null')),
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function shouldDisableSandbox(): bool
    {
        if (filter_var(env('CHROME_NO_SANDBOX', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return is_file('/.dockerenv');
    }
}

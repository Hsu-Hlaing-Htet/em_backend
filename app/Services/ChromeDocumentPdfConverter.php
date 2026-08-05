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

            $result = Process::timeout(120)->run([
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
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Chrome PDF conversion failed: '.$result->errorOutput());
            }

            if (! is_file($pdfPath)) {
                throw new RuntimeException('Chrome did not produce a PDF file.');
            }

            $pdf = file_get_contents($pdfPath);

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
}

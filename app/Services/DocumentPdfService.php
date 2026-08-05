<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use Illuminate\Http\Response;

class DocumentPdfService
{
    public function __construct(
        private readonly DocumentPdfConverter $converter,
    ) {}

    public function renderPdf(string $html): string
    {
        return $this->converter->convert($this->preparePrintableHtml($html));
    }

    public function downloadResponse(string $html, string $filename): Response
    {
        $pdf = $this->renderPdf($html);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    public function preparePrintableHtml(string $html): string
    {
        $stylesheetPath = (string) config('documents.stylesheet');
        $logoPath = (string) config('documents.logo');

        if (is_file($stylesheetPath)) {
            $css = (string) file_get_contents($stylesheetPath);
            $html = preg_replace(
                '/<link[^>]+contract-document\.css[^>]*>/i',
                '<style>'.$css.'</style>',
                $html,
                1,
            ) ?? $html;
        }

        if (is_file($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/jpeg';
            $dataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
            $html = preg_replace(
                '/src="[^"]*logo-dark\.jpg"/i',
                'src="'.$dataUri.'"',
                $html,
            ) ?? $html;
        }

        return $html;
    }
}

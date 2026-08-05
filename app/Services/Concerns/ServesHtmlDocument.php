<?php

namespace App\Services\Concerns;

use App\Services\DocumentPdfService;
use Illuminate\Http\Response;

trait ServesHtmlDocument
{
    protected function documentPdfService(): DocumentPdfService
    {
        return app(DocumentPdfService::class);
    }

    protected function downloadPdfResponse(string $html, string $filename): Response
    {
        return $this->documentPdfService()->downloadResponse($html, $filename);
    }

    protected function renderPdfBinary(string $html): string
    {
        return $this->documentPdfService()->renderPdf($html);
    }

    protected function downloadHtmlResponse(string $html, string $filename): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function exportHtmlResponse(string $html, string $filename): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

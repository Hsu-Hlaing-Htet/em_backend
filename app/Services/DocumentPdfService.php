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

    public function renderPreviewPdf(string $html): string
    {
        return $this->renderPdf($this->preservePreviewLayoutForPdf($html));
    }

    public function downloadResponse(string $html, string $filename): Response
    {
        $pdf = $this->renderPdf($html);

        return $this->pdfResponse($pdf, $filename);
    }

    public function downloadPreviewResponse(string $html, string $filename): Response
    {
        return $this->pdfResponse($this->renderPreviewPdf($html), $filename);
    }

    private function pdfResponse(string $pdf, string $filename): Response
    {
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

    public function preservePreviewLayoutForPdf(string $html): string
    {
        $html = $this->stripMediaBlocks($html, '/@media\s+print\s*\{/i');
        $html = $this->stripMediaBlocks($html, '/@media\s*\(\s*max-width\s*:\s*768px\s*\)\s*\{/i');
        $style = <<<'HTML'
<style id="contract-preview-pdf-overrides">
@page {
    size: A4 portrait;
    margin: 0;
}

html,
body,
#pdf-print.contract-doc-sheet {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

#pdf-print.contract-doc-sheet .contract-doc-parties {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 1px minmax(0, 1fr);
    gap: 1.5rem;
}

#pdf-print.contract-doc-sheet .contract-doc-party-divider {
    display: block;
}

#pdf-print.contract-doc-sheet .contract-doc-field,
#pdf-print.contract-doc-sheet .contract-doc-row {
    display: grid;
    grid-template-columns: minmax(5.5rem, 38%) minmax(0, 1fr);
    gap: 0.75rem;
}

#pdf-print.contract-doc-sheet .contract-doc-field-value,
#pdf-print.contract-doc-sheet .contract-doc-row-value {
    text-align: right;
}

#pdf-print.contract-doc-sheet .contract-doc-signatures {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

#pdf-print.contract-doc-sheet .contract-doc-section {
    break-inside: auto;
    page-break-inside: auto;
}

#pdf-print.contract-doc-sheet .contract-doc-body > .contract-doc-section:nth-of-type(2) {
    break-after: page;
    page-break-after: always;
}

#pdf-print.contract-doc-sheet .contract-doc-body > .contract-doc-section:nth-of-type(3) {
    break-inside: avoid;
    page-break-inside: avoid;
    padding-top: 28px;
}

#pdf-print.contract-doc-sheet .contract-doc-section-title {
    break-after: avoid;
    page-break-after: avoid;
}

#pdf-print.contract-doc-sheet .contract-doc-field,
#pdf-print.contract-doc-sheet .contract-doc-row,
#pdf-print.contract-doc-sheet .contract-doc-party,
#pdf-print.contract-doc-sheet .contract-doc-signature-line {
    break-inside: avoid;
    page-break-inside: avoid;
}

#pdf-print.contract-doc-sheet .contract-doc-signatures,
#pdf-print.contract-doc-sheet .contract-doc-rows--property {
    break-inside: avoid;
    page-break-inside: avoid;
}
</style>
HTML;

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $style."\n</head>", $html);
        }

        return $style."\n".$html;
    }

    private function stripMediaBlocks(string $html, string $pattern): string
    {
        $offset = 0;

        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $matches[0][1];
            $openBrace = strpos($html, '{', $start);

            if ($openBrace === false) {
                break;
            }

            $depth = 0;
            $length = strlen($html);
            $end = null;

            for ($index = $openBrace; $index < $length; $index++) {
                $char = $html[$index];

                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $index + 1;
                        break;
                    }
                }
            }

            if ($end === null) {
                break;
            }

            $html = substr($html, 0, $start).substr($html, $end);
            $offset = $start;
        }

        return $html;
    }
}

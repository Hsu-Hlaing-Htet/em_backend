<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportDocumentPreviewPdfRequest;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;

class DocumentPreviewPdfController extends Controller
{
    public function store(ExportDocumentPreviewPdfRequest $request, DocumentPdfService $documentPdfService): Response
    {
        $data = $request->validated();
        $filename = $data['filename'];

        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $documentPdfService->downloadPreviewResponse($data['html'], $filename);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportListPdfRequest;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;

class ListExportController extends Controller
{
    public function pdf(ExportListPdfRequest $request, DocumentPdfService $documentPdfService): Response
    {
        $data = $request->validated();
        $filename = $data['filename'];

        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        $html = view('exports.list', [
            'title' => $data['title'],
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'filters' => $data['filters'] ?? [],
            'generatedBy' => $data['generated_by'] ?? (auth()->user()?->name ?? 'Admin'),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'landscape' => (bool) ($data['landscape'] ?? true),
        ])->render();

        return $documentPdfService->downloadResponse($html, $filename);
    }
}

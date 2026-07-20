<?php

namespace App\Services\Concerns;

use Illuminate\Http\Response;

trait ServesHtmlDocument
{
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

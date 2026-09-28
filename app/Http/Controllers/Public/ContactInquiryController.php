<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactInquiryRequest;
use App\Services\ContactInquiryService;
use Illuminate\Http\JsonResponse;

class ContactInquiryController extends Controller
{
    public function store(
        StoreContactInquiryRequest $request,
        ContactInquiryService $contactInquiryService,
    ): JsonResponse {
        $contactInquiryService->create($request->validated());

        return response()->json([
            'message' => 'Your message has been sent successfully.',
        ], 201);
    }
}

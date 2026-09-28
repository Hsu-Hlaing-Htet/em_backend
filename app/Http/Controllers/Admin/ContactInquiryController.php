<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ContactInquiryResource;
use App\Models\ContactInquiry;
use App\Services\ContactInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactInquiryController extends Controller
{
    public function index(Request $request, ContactInquiryService $contactInquiryService): JsonResponse
    {
        $this->authorize('viewAny', ContactInquiry::class);

        $paginator = $contactInquiryService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ContactInquiryResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(
        ContactInquiry $contactInquiry,
        ContactInquiryService $contactInquiryService,
    ): JsonResponse {
        $this->authorize('view', $contactInquiry);

        if ($contactInquiry->status === ContactInquiry::STATUS_NEW) {
            $contactInquiry = $contactInquiryService->markAsRead($contactInquiry);
        }

        return response()->json([
            'data' => new ContactInquiryResource($contactInquiry),
        ]);
    }
}

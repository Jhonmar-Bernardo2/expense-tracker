<?php

namespace App\Http\Controllers;

use App\Repositories\ApprovalMemoRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalMemoAttachmentController extends Controller
{
    public function __construct(
        private readonly ApprovalMemoRepository $approvalMemoRepository,
    ) {
    }

    public function download(Request $request, int $approvalMemo, int $attachment): StreamedResponse
    {
        $approvalMemo = $this->approvalMemoRepository->findForViewerOrFail(
            $request->user(),
            $approvalMemo,
        );

        $attachment = $approvalMemo->attachments()->findOrFail($attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}

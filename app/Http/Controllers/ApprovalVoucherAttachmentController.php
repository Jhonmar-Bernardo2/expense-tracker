<?php

namespace App\Http\Controllers;

use App\Repositories\ApprovalVoucherRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalVoucherAttachmentController extends Controller
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
    ) {
    }

    public function download(Request $request, int $approvalVoucher, int $attachment): StreamedResponse
    {
        $approvalVoucher = $this->approvalVoucherRepository->findForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $attachment = $approvalVoucher->attachments()->findOrFail($attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}

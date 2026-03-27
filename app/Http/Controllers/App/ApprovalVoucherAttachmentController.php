<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Repositories\ApprovalVoucherAttachmentRepository;
use App\Repositories\ApprovalVoucherRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalVoucherAttachmentController extends Controller
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
        private readonly ApprovalVoucherAttachmentRepository $approvalVoucherAttachmentRepository,
    ) {
    }

    public function download(Request $request, int $approvalVoucher, int $attachment): StreamedResponse
    {
        $approvalVoucher = $this->approvalVoucherRepository->findForViewerOrFail(
            $request->user(),
            $approvalVoucher,
        );

        $attachment = $this->approvalVoucherAttachmentRepository->findForVoucherOrFail(
            $approvalVoucher,
            $attachment,
        );

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}

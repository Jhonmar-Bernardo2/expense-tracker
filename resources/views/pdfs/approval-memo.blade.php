<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $approvalMemo->memo_no }}</title>
    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #111827;
            margin: 0;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .document-header {
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .document-kicker {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .document-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .document-subtitle {
            font-size: 12px;
            color: #374151;
        }

        .status-table,
        .details-table,
        .remarks-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .status-table {
            margin-bottom: 16px;
        }

        .status-cell {
            border: 1px solid #9ca3af;
            background: #f9fafb;
            padding: 10px 12px;
        }

        .status-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #4b5563;
            margin-bottom: 4px;
        }

        .status-value {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 18px 0 8px;
        }

        .details-table td,
        .remarks-table td {
            border: 1px solid #9ca3af;
            padding: 10px 12px;
            vertical-align: top;
        }

        .details-table td {
            width: 50%;
        }

        .remarks-table td {
            width: 50%;
            min-height: 120px;
        }

        .field-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #4b5563;
            margin-bottom: 5px;
        }

        .field-value {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .field-subvalue {
            font-size: 11px;
            color: #374151;
        }

        .body-copy {
            font-size: 12px;
            color: #111827;
            white-space: pre-line;
        }

        .signature-table {
            margin-top: 34px;
        }

        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 8px;
        }

        .signature-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .signature-role,
        .signature-date {
            font-size: 11px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    @php
        $formatDateTime = static fn ($value) => $value?->timezone('Asia/Manila')->format('F j, Y g:i A') ?? '-';
    @endphp

    <div class="document-header">
        <div class="document-kicker">Approval Memo</div>
        <div class="document-title">{{ $approvalMemo->memo_no }}</div>
        <div class="document-subtitle">
            System-generated approval memo for final request submission.
        </div>
    </div>

    <table class="status-table" role="presentation">
        <tr>
            <td class="status-cell">
                <div class="status-label">Status</div>
                <div class="status-value">{{ $approvalMemo->status->label() }}</div>
                <div class="field-subvalue">Approved at {{ $formatDateTime($approvalMemo->approved_at) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Request Details</div>
    <table class="details-table" role="presentation">
        <tr>
            <td>
                <div class="field-label">Requester</div>
                <div class="field-value">{{ $approvalMemo->requestedBy?->name ?? '-' }}</div>
                <div class="field-subvalue">{{ $approvalMemo->requestedBy?->email ?? '-' }}</div>
            </td>
            <td>
                <div class="field-label">Department</div>
                <div class="field-value">{{ $approvalMemo->department?->name ?? '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="field-label">Module</div>
                <div class="field-value">{{ $approvalMemo->module->label() }}</div>
            </td>
            <td>
                <div class="field-label">Action</div>
                <div class="field-value">{{ $approvalMemo->action->label() }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="field-label">Approved By</div>
                <div class="field-value">{{ $approvalMemo->approvedBy?->name ?? '-' }}</div>
                <div class="field-subvalue">{{ $approvalMemo->approvedBy?->email ?? '-' }}</div>
            </td>
            <td>
                <div class="field-label">Submitted</div>
                <div class="field-value">{{ $formatDateTime($approvalMemo->submitted_at) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Remarks</div>
    <table class="remarks-table" role="presentation">
        <tr>
            <td>
                <div class="field-label">Purpose or Remarks</div>
                <div class="body-copy">{{ $approvalMemo->remarks ?? 'No remarks provided.' }}</div>
            </td>
            <td>
                <div class="field-label">Admin Remarks</div>
                <div class="body-copy">{{ $approvalMemo->admin_remarks ?? 'No admin remarks recorded.' }}</div>
            </td>
        </tr>
    </table>

    <table class="signature-table" role="presentation">
        <tr>
            <td>
                <div class="signature-line">
                    <div class="signature-name">{{ $approvalMemo->requestedBy?->name ?? '-' }}</div>
                    <div class="signature-role">Requested by</div>
                </div>
            </td>
            <td>
                <div class="signature-line">
                    <div class="signature-name">{{ $approvalMemo->approvedBy?->name ?? '-' }}</div>
                    <div class="signature-role">Approved by</div>
                    <div class="signature-date">{{ $formatDateTime($approvalMemo->approved_at) }}</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

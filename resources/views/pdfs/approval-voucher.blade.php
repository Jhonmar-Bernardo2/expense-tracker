@php
    $currentPayload = $approval_voucher['after_payload'] ?? $approval_voucher['before_payload'] ?? [];
    $isTransaction = $approval_voucher['module'] === 'transaction';
    $isAllocation = $approval_voucher['module'] === 'allocation';
    $isDelete = $approval_voucher['action'] === 'delete';
    $fields = $isTransaction
        ? ['department_id', 'type', 'category_id', 'title', 'amount', 'transaction_date', 'description']
        : ($isAllocation
            ? ['department_id', 'month', 'year', 'amount_limit']
            : ['department_id', 'category_id', 'month', 'year', 'amount_limit']);
    $fieldLabels = [
        'department_id' => 'Department',
        'type' => 'Type',
        'category_id' => 'Category',
        'title' => 'Title',
        'amount' => 'Amount',
        'transaction_date' => 'Date',
        'description' => 'Description',
        'month' => 'Month',
        'year' => 'Year',
        'amount_limit' => 'Monthly limit',
    ];
    $departmentsById = collect($departments)->keyBy('id');
    $categoriesById = collect($categories)->keyBy('id');
    $notes = trim((string) ($approval_voucher['remarks'] ?? ''));
    $rejectionReason = trim((string) ($approval_voucher['rejection_reason'] ?? ''));
    $actionSummary = match ($approval_voucher['module']) {
        'allocation' => $approval_voucher['action'] === 'delete'
            ? 'Allocation removal request'
            : 'Monthly allocation request',
        default => match ($approval_voucher['action']) {
            'create' => 'New record request',
            'update' => 'Change request',
            default => 'Delete request',
        },
    };
    $formatText = static fn (?string $value): string => $value === null || $value === ''
        ? '-'
        : str($value)->replace('_', ' ')->headline()->toString();
    $formatCurrency = static fn ($value): string => '&#8369;'.number_format((float) $value, 2);
    $monthLabel = static function ($month): string {
        $month = (int) $month;

        if ($month < 1 || $month > 12) {
            return '-';
        }

        return date('F', mktime(0, 0, 0, $month, 1));
    };
    $formatDate = static function (?string $value): string {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            return \Carbon\CarbonImmutable::parse($value)->format('F j, Y');
        } catch (\Throwable) {
            return $value;
        }
    };
    $formatDateTime = static function (?string $value): string {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            return \Carbon\CarbonImmutable::parse($value)->format('F j, Y \a\t g:i A');
        } catch (\Throwable) {
            return $value;
        }
    };
    $fieldValue = static function ($data, string $field) use (
        $approval_voucher,
        $categoriesById,
        $departmentsById,
        $formatCurrency,
        $formatText,
        $formatDate,
        $monthLabel
    ): string {
        if (! is_array($data)) {
            return '-';
        }

        if ($field === 'department_id') {
            $department = $departmentsById->get($data['department_id'] ?? null);

            return (string) data_get($department, 'name', data_get($approval_voucher, 'department.name', '-'));
        }

        if ($field === 'category_id') {
            return (string) data_get($categoriesById->get($data['category_id'] ?? null), 'name', '-');
        }

        if ($field === 'type') {
            return $formatText($data['type'] ?? null);
        }

        if ($field === 'month') {
            return $monthLabel($data['month'] ?? null);
        }

        if ($field === 'transaction_date') {
            return $formatDate($data['transaction_date'] ?? null);
        }

        if (in_array($field, ['amount', 'amount_limit'], true)) {
            return $formatCurrency($data[$field] ?? 0);
        }

        $value = $data[$field] ?? null;

        return $value === null || $value === '' ? '-' : e((string) $value);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ data_get($approval_voucher, 'voucher_no', 'Approval Voucher') }} PDF</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.34;
        }

        .document {
            width: 100%;
            min-height: 286mm;
            border: 1px solid #94a3b8;
            background: #ffffff;
        }

        .header-table,
        .meta-table,
        .pair-table,
        .signature-table {
            width: 100%;
            border-collapse: separate;
            table-layout: fixed;
        }

        .header-table,
        .meta-table {
            border-spacing: 0;
        }

        .pair-table {
            border-spacing: 5px 0;
        }

        .document-header {
            padding: 9px 9px 8px;
            border-bottom: 1px solid #94a3b8;
            background: #f8fafc;
        }

        .document-brand {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        .document-brand-subtitle {
            margin-top: 3px;
            color: #475569;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .document-title {
            margin-top: 7px;
            font-size: 23px;
            font-weight: 700;
            line-height: 1.05;
        }

        .document-subject {
            margin-top: 5px;
            color: #475569;
            font-size: 9.8px;
            line-height: 1.28;
        }

        .document-reference {
            width: 31%;
            padding-left: 12px;
            border-left: 1px solid #cbd5e1;
            text-align: right;
            vertical-align: top;
        }

        .document-status {
            display: inline-block;
            padding: 3px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.24em;
            text-transform: uppercase;
        }

        .document-status-approved {
            background: #eff6f1;
            border-color: #bfd7c8;
            color: #1f5c43;
        }

        .document-status-pending {
            background: #fff8eb;
            border-color: #e8d2a3;
            color: #8a6117;
        }

        .document-status-rejected {
            background: #fef2f2;
            border-color: #efc2c2;
            color: #9f2f2f;
        }

        .document-status-default {
            background: #f8fafc;
            color: #334155;
        }

        .document-reference-label {
            margin-top: 8px;
            color: #64748b;
            font-size: 8px;
            letter-spacing: 0.24em;
            text-transform: uppercase;
        }

        .document-reference-value {
            margin-top: 3px;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.1;
        }

        .meta-strip {
            margin-top: 8px;
            border: 1px solid #94a3b8;
            background: #ffffff;
        }

        .meta-table td {
            height: 11.5mm;
            padding: 4px 6px 5px;
            border-right: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .meta-table td:last-child {
            border-right: 0;
        }

        .document-meta-label {
            color: #64748b;
            font-size: 7.8px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .document-meta-value {
            margin-top: 3px;
            color: #0f172a;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.18;
        }

        .document-body {
            padding: 5px 7px 7px;
        }

        .document-section {
            margin-top: 5px;
            border: 1px solid #94a3b8;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .document-section:first-child {
            margin-top: 0;
        }

        .section-header {
            padding: 5px 8px 5px;
            border-bottom: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .section-content {
            padding: 6px 5px 6px;
        }

        .section-kicker {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .section-title {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
        }

        .panel,
        .note-panel {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            vertical-align: top;
            page-break-inside: avoid;
        }

        .panel-muted,
        .note-panel {
            background: #f8fafc;
        }

        .panel-header {
            padding: 5px 7px 5px;
            border-bottom: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .panel-title {
            font-size: 9.8px;
            font-weight: 700;
        }

        .panel-description {
            margin-top: 2px;
            color: #475569;
            font-size: 8.6px;
            line-height: 1.18;
        }

        .panel-body {
            padding: 5px 7px 6px;
        }

        .info-table,
        .compare-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table th,
        .info-table td,
        .compare-table th,
        .compare-table td {
            padding: 3px 0;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .info-table tr:last-child th,
        .info-table tr:last-child td,
        .compare-table tr:last-child th,
        .compare-table tr:last-child td {
            border-bottom: 0;
        }

        .info-table th,
        .compare-table th {
            width: 40%;
            text-align: left;
            color: #475569;
            font-size: 8.8px;
            font-weight: 600;
        }

        .info-table td {
            text-align: right;
            color: #0f172a;
            font-size: 8.8px;
            font-weight: 700;
        }

        .compare-table td {
            text-align: left;
            color: #0f172a;
            font-size: 8.8px;
            font-weight: 700;
        }

        .value-support {
            margin-top: 1px;
            color: #475569;
            font-size: 8px;
            font-weight: 500;
            line-height: 1.18;
        }

        .summary-panel {
            height: 40mm;
        }

        .compare-panel {
            height: 68mm;
        }

        .empty-state {
            padding: 7px;
            border: 1px dashed #94a3b8;
            background: #f1f5f9;
            color: #475569;
            font-size: 8.6px;
            line-height: 1.18;
        }

        .note-panel {
            height: 18mm;
            padding: 6px 7px 7px;
        }

        .note-copy {
            margin-top: 4px;
            color: #475569;
            font-size: 8.6px;
            line-height: 1.22;
            white-space: pre-line;
        }

        .signatory-section {
            margin-top: 5px;
        }

        .signature-table {
            border-spacing: 10px 0;
        }

        .signature-block {
            height: 20mm;
            vertical-align: bottom;
        }

        .signature-line {
            padding-top: 5px;
            border-top: 1px solid #94a3b8;
        }

        .signature-name {
            font-size: 8.8px;
            font-weight: 700;
        }

        .signature-role {
            margin-top: 2px;
            color: #64748b;
            font-size: 7.8px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .signature-date {
            margin-top: 3px;
            color: #475569;
            font-size: 7.8px;
            line-height: 1.18;
        }
    </style>
</head>
<body>
    @php
        $statusClass = match (data_get($approval_voucher, 'status')) {
            'approved' => 'document-status-approved',
            'pending_approval' => 'document-status-pending',
            'rejected' => 'document-status-rejected',
            default => 'document-status-default',
        };
    @endphp

    <div class="document">
        <div class="document-header">
            <table class="header-table">
                <tr>
                    <td style="width: 66%; vertical-align: top;">
                        <div class="document-brand">Expense Tracker</div>
                        <div class="document-brand-subtitle">Official approval copy</div>
                        <div class="document-title">Approval Voucher</div>
                        <div class="document-subject">
                            {{ data_get($approval_voucher, 'subject', 'No subject provided.') }}
                        </div>
                    </td>
                    <td class="document-reference">
                        <div class="document-status {{ $statusClass }}">
                            {{ data_get($approval_voucher, 'status_label', '-') }}
                        </div>
                        <div class="document-reference-label">Voucher No.</div>
                        <div class="document-reference-value">
                            {{ data_get($approval_voucher, 'voucher_no', '-') }}
                        </div>
                    </td>
                </tr>
            </table>

            <div class="meta-strip">
                <table class="meta-table">
                    <tr>
                        <td>
                            <div class="document-meta-label">Module</div>
                            <div class="document-meta-value">
                                {{ data_get($approval_voucher, 'module_label', '-') }}
                            </div>
                        </td>
                        <td>
                            <div class="document-meta-label">Action</div>
                            <div class="document-meta-value">
                                {{ data_get($approval_voucher, 'action_label', '-') }}
                            </div>
                        </td>
                        <td>
                            <div class="document-meta-label">Department</div>
                            <div class="document-meta-value">
                                {{ data_get($approval_voucher, 'department.name', data_get($departmentsById->get($currentPayload['department_id'] ?? null), 'name', '-')) }}
                            </div>
                        </td>
                        <td>
                            <div class="document-meta-label">Prepared By</div>
                            <div class="document-meta-value">
                                {{ data_get($approval_voucher, 'requested_by_user.name', '-') }}
                            </div>
                        </td>
                        <td>
                            <div class="document-meta-label">Prepared On</div>
                            <div class="document-meta-value">
                                {{ $formatDateTime(data_get($approval_voucher, 'submitted_at') ?? data_get($approval_voucher, 'created_at')) }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="document-body">
            <div class="document-section">
                <div class="section-header">
                    <div class="section-kicker">Voucher Overview</div>
                    <div class="section-title">Request Summary</div>
                </div>

                <div class="section-content">
                    <table class="pair-table">
                        <tr>
                            <td class="panel panel-muted summary-panel" style="width: 50%;">
                                <div class="panel-header">
                                    <div class="panel-title">Request Information</div>
                                </div>
                                <div class="panel-body">
                                    <table class="info-table">
                                        <tr>
                                            <th>Module</th>
                                            <td>{{ data_get($approval_voucher, 'module_label', '-') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Action</th>
                                            <td>
                                                <div>{{ data_get($approval_voucher, 'action_label', '-') }}</div>
                                                <div class="value-support">{{ $actionSummary }}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Department</th>
                                            <td>
                                                {{ data_get($approval_voucher, 'department.name', data_get($departmentsById->get($currentPayload['department_id'] ?? null), 'name', '-')) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Preparer</th>
                                            <td>
                                                <div>{{ data_get($approval_voucher, 'requested_by_user.name', '-') }}</div>
                                                <div class="value-support">
                                                    {{ data_get($approval_voucher, 'requested_by_user.email', 'No email available') }}
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                            <td class="panel panel-muted summary-panel" style="width: 50%;">
                                <div class="panel-header">
                                    <div class="panel-title">Processing Timeline</div>
                                </div>
                                <div class="panel-body">
                                    <table class="info-table">
                                        <tr>
                                            <th>Created</th>
                                            <td>{{ $formatDateTime(data_get($approval_voucher, 'created_at')) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Submitted</th>
                                            <td>{{ $formatDateTime(data_get($approval_voucher, 'submitted_at')) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Approved</th>
                                            <td>{{ $formatDateTime(data_get($approval_voucher, 'approved_at')) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Applied</th>
                                            <td>{{ $formatDateTime(data_get($approval_voucher, 'applied_at')) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="document-section">
                <div class="section-header">
                    <div class="section-kicker">Record Comparison</div>
                    <div class="section-title">Current and Requested Values</div>
                </div>

                <div class="section-content">
                    <table class="pair-table">
                        <tr>
                            <td class="panel compare-panel" style="width: 50%;">
                                <div class="panel-header">
                                    <div class="panel-title">Current Details</div>
                                    <div class="panel-description">
                                        Represents the existing record values before the request is processed.
                                    </div>
                                </div>
                                <div class="panel-body">
                                    @if (data_get($approval_voucher, 'before_payload') === null)
                                        <div class="empty-state">No prior final record snapshot.</div>
                                    @else
                                        <table class="compare-table">
                                            @foreach ($fields as $field)
                                                <tr>
                                                    <th>{{ $fieldLabels[$field] ?? $field }}</th>
                                                    <td>{!! $fieldValue(data_get($approval_voucher, 'before_payload'), $field) !!}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @endif
                                </div>
                            </td>
                            <td class="panel compare-panel" style="width: 50%;">
                                <div class="panel-header">
                                    <div class="panel-title">Updated Details</div>
                                    <div class="panel-description">
                                        Represents the requested changes to be applied upon approval.
                                    </div>
                                </div>
                                <div class="panel-body">
                                    @if (data_get($approval_voucher, 'after_payload') === null)
                                        <div class="empty-state">
                                            {{ $isDelete ? 'Approval will void or archive the final record.' : 'No updated payload saved.' }}
                                        </div>
                                    @else
                                        <table class="compare-table">
                                            @foreach ($fields as $field)
                                                <tr>
                                                    <th>{{ $fieldLabels[$field] ?? $field }}</th>
                                                    <td>{!! $fieldValue(data_get($approval_voucher, 'after_payload'), $field) !!}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($notes !== '' || $rejectionReason !== '')
                <div class="document-section">
                    <div class="section-header">
                        <div class="section-kicker">Review Notes</div>
                        <div class="section-title">Supporting Remarks</div>
                    </div>

                    <div class="section-content">
                        <table class="pair-table">
                            <tr>
                                @if ($notes !== '')
                                    <td class="note-panel" style="width: {{ $rejectionReason !== '' ? '50%' : '100%' }};">
                                        <div class="panel-title">Preparer Notes</div>
                                        <div class="note-copy">{{ $notes }}</div>
                                    </td>
                                @endif

                                @if ($rejectionReason !== '')
                                    <td class="note-panel" style="width: {{ $notes !== '' ? '50%' : '100%' }};">
                                        <div class="panel-title">Rejection Reason</div>
                                        <div class="note-copy">{{ $rejectionReason }}</div>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </div>
                </div>
            @endif

            <div class="document-section signatory-section">
                <div class="section-header">
                    <div class="section-kicker">Authorization</div>
                    <div class="section-title">Signatories</div>
                </div>

                <div class="section-content">
                    <table class="signature-table">
                        <tr>
                            <td class="signature-block" style="width: 50%;">
                                <div class="signature-line">
                                    <div class="signature-name">{{ data_get($approval_voucher, 'requested_by_user.name', '-') }}</div>
                                    <div class="signature-role">Prepared by</div>
                                    <div class="signature-date">
                                        {{ $formatDateTime(data_get($approval_voucher, 'submitted_at') ?? data_get($approval_voucher, 'created_at')) }}
                                    </div>
                                </div>
                            </td>
                            <td class="signature-block" style="width: 50%;">
                                <div class="signature-line">
                                    <div class="signature-name">
                                        {{ data_get($approval_voucher, 'approved_by_user.name', 'Pending approver signature') }}
                                    </div>
                                    <div class="signature-role">Approved by</div>
                                    <div class="signature-date">
                                        {{ $formatDateTime(data_get($approval_voucher, 'approved_at') ?? data_get($approval_voucher, 'rejected_at')) }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

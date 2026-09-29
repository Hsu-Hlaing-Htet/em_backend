<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        {!! file_get_contents(resource_path('documents/contract-document.css')) !!}

        /*
         * List exports: fill the printable area.
         * Do NOT set sheet width to full paper size while also using @page margins —
         * Chrome scales the document down and the table looks artificially narrow.
         *
         * Page numbers come from Chrome CDP footerTemplate (see ChromeDocumentPdfConverter),
         * not CSS counter(page), which renders as Page 0 on fixed/in-flow footers.
         */
        @page {
            size: {{ $landscape ? 'A4 landscape' : 'A4 portrait' }};
            margin: 8mm 8mm 12mm 8mm;
        }

        html,
        body {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
        }

        @media print {
            html {
                counter-reset: none;
            }
        }

        .pdf-sheet.pdf-sheet--list {
            --pdf-ink: #1c1c1c;
            --pdf-muted: #6b6560;
            --pdf-line: rgba(28, 28, 28, 0.12);
            --pdf-accent: rgba(122, 49, 73, 0.55);

            width: 100% !important;
            max-width: none !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            background: #fff !important;
            overflow-wrap: break-word;
            font-size: 10pt;
            line-height: 1.35;
        }

        .pdf-sheet--list .pdf-document-lead {
            break-inside: avoid;
        }

        .pdf-sheet--list .pdf-head {
            margin-bottom: 0.45rem;
        }

        .pdf-sheet--list .pdf-head-row {
            gap: 0.75rem;
            margin-bottom: 0.4rem;
        }

        .pdf-sheet--list .pdf-logo {
            width: 1.65rem;
            height: 1.65rem;
        }

        .pdf-sheet--list .pdf-brand {
            gap: 0.65rem;
        }

        .pdf-sheet--list .pdf-company {
            font-size: 9pt;
            letter-spacing: 0.1em;
        }

        .pdf-sheet--list .pdf-company-sub {
            margin-top: 0.1rem;
            font-size: 7pt;
        }

        .pdf-sheet--list .pdf-meta-item {
            margin-bottom: 0.25rem;
            gap: 0.05rem;
        }

        .pdf-sheet--list .pdf-meta-label {
            font-size: 6pt;
            letter-spacing: 0.12em;
        }

        .pdf-sheet--list .pdf-meta-value {
            font-size: 8pt;
        }

        .pdf-sheet--list .pdf-doc-title {
            margin: 0;
            font-size: 12pt;
            letter-spacing: 0.12em;
        }

        .pdf-sheet--list .pdf-rule--accent {
            width: 3rem;
            margin: 0.3rem auto 0;
        }

        .list-export-filters {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 1.25rem;
            row-gap: 0.15rem;
            margin: 0.4rem 0 0.55rem;
            padding: 0;
            list-style: none;
            font-size: 7.5pt;
            line-height: 1.3;
            color: var(--pdf-muted);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .list-export-filters li {
            margin: 0;
            min-width: 0;
        }

        .list-export-filters strong {
            color: var(--pdf-ink);
            font-weight: 600;
        }

        .pdf-sheet--list .pdf-block {
            margin-bottom: 0;
        }

        .pdf-sheet--list .doc-table-wrap {
            margin-top: 0;
            overflow: visible;
        }

        .pdf-sheet--list .doc-table {
            width: 100% !important;
            min-width: 100% !important;
            max-width: none !important;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9pt;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .pdf-sheet--list .doc-table thead {
            display: table-header-group;
        }

        .pdf-sheet--list .doc-table th,
        .pdf-sheet--list .doc-table td {
            border: none;
            border-bottom: 1px solid rgba(28, 28, 28, 0.12);
            padding: 5px 9px;
            vertical-align: top;
            text-align: left;
            overflow-wrap: break-word;
            word-break: normal;
            box-sizing: border-box;
        }

        .pdf-sheet--list .doc-table th {
            background: rgba(122, 49, 73, 0.07);
            border-bottom: 1px solid rgba(122, 49, 73, 0.22);
            font-size: 7.5pt;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #5c3d47;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        .pdf-sheet--list .doc-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .pdf-sheet--list .list-col--nowrap {
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: keep-all;
            overflow: hidden;
        }

        .pdf-sheet--list .list-col--align-center {
            text-align: center;
        }

        .pdf-sheet--list .list-col--align-right {
            text-align: right;
        }

        .pdf-sheet--list .list-col--issued_date,
        .pdf-sheet--list .list-col--due_date,
        .pdf-sheet--list .list-col--payment_status,
        .pdf-sheet--list .list-col--status,
        .pdf-sheet--list .list-col--payment_date,
        .pdf-sheet--list .list-col--date,
        .pdf-sheet--list .list-col--created_at {
            padding-left: 11px;
            padding-right: 11px;
        }
    </style>
</head>
<body>
    <article id="pdf-print" class="pdf-sheet pdf-sheet--list">
        <div class="pdf-document-lead">
            <header class="pdf-head">
                <div class="pdf-head-row">
                    <div class="pdf-brand">
                        @php
                            $logoPath = resource_path('documents/logo-dark.jpg');
                            $logoSrc = is_file($logoPath)
                                ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath))
                                : '';
                        @endphp
                        @if ($logoSrc)
                            <img src="{{ $logoSrc }}" alt="Rosewood Royale" class="pdf-logo">
                        @endif
                        <div class="pdf-brand-text">
                            <p class="pdf-company">Rosewood Royale Residences</p>
                            <p class="pdf-company-sub">Residences &amp; Property Management</p>
                        </div>
                    </div>
                    <div class="pdf-head-meta">
                        <div class="pdf-meta-item">
                            <span class="pdf-meta-label">Generated</span>
                            <span class="pdf-meta-value">{{ $generatedAt }}</span>
                        </div>
                        <div class="pdf-meta-item">
                            <span class="pdf-meta-label">Generated By</span>
                            <span class="pdf-meta-value">{{ $generatedBy }}</span>
                        </div>
                    </div>
                </div>
                <h1 class="pdf-doc-title">{{ $title }}</h1>
                <div class="pdf-rule pdf-rule--accent"></div>
            </header>
        </div>

        @if (! empty($filters))
            <ul class="list-export-filters">
                @foreach ($filters as $filter)
                    <li><strong>{{ $filter['label'] }}:</strong> {{ $filter['value'] !== '' && $filter['value'] !== null ? $filter['value'] : 'All' }}</li>
                @endforeach
            </ul>
        @endif

        <section class="pdf-block">
            <div class="doc-table-wrap">
                <table class="doc-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                @php
                                    $fieldSlug = preg_replace('/[^a-z0-9_-]/i', '-', $column['field']);
                                    $align = $column['align'] ?? 'left';
                                    $width = $column['width'] ?? null;
                                    $nowrap = ! empty($column['nowrap']);
                                    $classes = trim(implode(' ', array_filter([
                                        'list-col',
                                        'list-col--'.$fieldSlug,
                                        in_array($align, ['center', 'right'], true) ? 'list-col--align-'.$align : null,
                                        $nowrap ? 'list-col--nowrap' : null,
                                    ])));
                                    $style = $width ? 'width: '.$width.';' : null;
                                @endphp
                                <th class="{{ $classes }}"@if ($style) style="{{ $style }}"@endif>{{ $column['header'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    @php
                                        $fieldSlug = preg_replace('/[^a-z0-9_-]/i', '-', $column['field']);
                                        $align = $column['align'] ?? 'left';
                                        $nowrap = ! empty($column['nowrap']);
                                        $classes = trim(implode(' ', array_filter([
                                            'list-col',
                                            'list-col--'.$fieldSlug,
                                            in_array($align, ['center', 'right'], true) ? 'list-col--align-'.$align : null,
                                            $nowrap ? 'list-col--nowrap' : null,
                                        ])));
                                    @endphp
                                    <td class="{{ $classes }}">{{ $row[$column['field']] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(count($columns), 1) }}">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </article>
</body>
</html>

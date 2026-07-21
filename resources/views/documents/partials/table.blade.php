<section class="pdf-block">
    <h2 class="pdf-block-title">{{ $title }}</h2>
    <div class="pdf-rule"></div>
    <div class="doc-table-wrap">
        <table class="doc-table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>{{ $row[$column['key']] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}">{{ $emptyMessage ?? 'No records found.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

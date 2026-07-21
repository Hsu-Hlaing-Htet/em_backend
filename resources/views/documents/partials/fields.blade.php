<section class="pdf-block">
    <h2 class="pdf-block-title">{{ $title }}</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        @foreach ($fields as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd @class(['pdf-row-full' => ($item['full'] ?? false)])>{{ $item['value'] }}</dd>
        @endforeach
    </dl>
</section>

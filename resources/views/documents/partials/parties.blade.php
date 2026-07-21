<section class="pdf-block">
    <h2 class="pdf-block-title">{{ $title ?? 'Billing Parties' }}</h2>
    <div class="pdf-rule"></div>
    <div class="pdf-parties">
        <div class="pdf-party">
            <p class="pdf-party-label">{{ $leftLabel }}</p>
            <dl class="pdf-rows">
                @foreach ($leftParty as $item)
                    <dt>{{ $item['label'] }}</dt>
                    <dd>{{ $item['value'] }}</dd>
                @endforeach
            </dl>
        </div>
        <div class="pdf-party">
            <p class="pdf-party-label">{{ $rightLabel }}</p>
            <dl class="pdf-rows">
                @foreach ($rightParty as $item)
                    <dt>{{ $item['label'] }}</dt>
                    <dd>{{ $item['value'] }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
</section>

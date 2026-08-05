<section class="pdf-block">
    <h2 class="pdf-block-title">The Parties</h2>
    <div class="pdf-rule"></div>
    <div class="pdf-parties">
        <div class="pdf-party">
            <p class="pdf-party-label">Tenant</p>
            <dl class="pdf-rows">
                @foreach ($document['customer'] as $item)
                    <dt>{{ $item['label'] }}</dt>
                    <dd>{{ $item['value'] }}</dd>
                @endforeach
            </dl>
        </div>
        <div class="pdf-party">
            <p class="pdf-party-label">Landlord</p>
            <dl class="pdf-rows">
                @foreach ($document['company'] as $item)
                    <dt>{{ $item['label'] }}</dt>
                    <dd>{{ $item['value'] }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
</section>

<section class="pdf-block">
    <h2 class="pdf-block-title">The Property</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        @foreach ($document['property'] as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd>{{ $item['value'] }}</dd>
        @endforeach
    </dl>
</section>

<section class="pdf-block">
    <h2 class="pdf-block-title">Terms of Rent</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        @foreach ($document['contract'] as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd>{{ $item['value'] }}</dd>
        @endforeach
    </dl>
</section>

<section class="pdf-block">
    <h2 class="pdf-block-title">Payment Terms</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        @foreach ($document['payment'] as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd>{{ $item['value'] }}</dd>
        @endforeach
        @if (! empty($document['installment']))
            <dt>Remaining After Deposit</dt>
            <dd>{{ $document['installment']['remainingAfterDeposit'] }}</dd>
            <dt>Installment Period</dt>
            <dd>{{ $document['installment']['duration'] }}</dd>
            <dt>Estimated Monthly Payment</dt>
            <dd>{{ $document['installment']['monthlyPayment'] }}</dd>
        @endif
    </dl>
</section>

<section class="pdf-block">
    <h2 class="pdf-block-title">Special Conditions</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        <dt>Purchaser Obligations</dt>
        <dd>
            The Purchaser shall pay all amounts due under this Agreement in accordance with
            the agreed payment schedule, maintain the property in good condition, and comply
            with all applicable building rules and regulations.
        </dd>
        <dt>Seller Obligations</dt>
        <dd>
            The Seller shall deliver clear title to the property, provide all necessary
            documentation, and ensure the property is transferred in the condition agreed
            upon at the time of execution.
        </dd>
        <dt>Remarks</dt>
        <dd class="pdf-row-full">{{ $document['remarks'] }}</dd>
    </dl>
</section>

<section class="pdf-block">
    <h2 class="pdf-block-title">Authorization</h2>
    <div class="pdf-rule"></div>
    <dl class="pdf-rows">
        @foreach ($document['approval'] as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd>{{ $item['value'] }}</dd>
        @endforeach
    </dl>
</section>

<section class="pdf-block pdf-block--exec">
    <h2 class="pdf-block-title">Execution</h2>
    <div class="pdf-rule"></div>
    <p class="pdf-witness">
        IN WITNESS WHEREOF, the parties hereto have executed this Property Rent Agreement
        as of the date first written above.
    </p>
    <div class="pdf-signs">
        @foreach ($document['signatures'] as $signature)
            <div class="pdf-sign">
                <div class="pdf-sign-line"></div>
                <p class="pdf-sign-name">{{ $signature['name'] }}</p>
                <p class="pdf-sign-role">{{ $signature['label'] }}</p>
                <p class="pdf-sign-date">Date: ____________________</p>
            </div>
        @endforeach
    </div>
</section>

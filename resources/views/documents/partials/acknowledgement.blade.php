<section class="pdf-block pdf-block--exec">
    <h2 class="pdf-block-title">{{ $title ?? 'Acknowledgement' }}</h2>
    <div class="pdf-rule"></div>
    <p class="pdf-witness">{{ $message }}</p>
    <div class="pdf-signs">
        <div class="pdf-sign">
            <div class="pdf-sign-line"></div>
            <p class="pdf-sign-name">{{ $leftName ?? '________________' }}</p>
            <p class="pdf-sign-role">{{ $leftRole ?? 'Customer Signature' }}</p>
            <p class="pdf-sign-date">Date: ____________________</p>
        </div>
        <div class="pdf-sign">
            <div class="pdf-sign-line"></div>
            <p class="pdf-sign-name">{{ $rightName ?? '________________' }}</p>
            <p class="pdf-sign-role">{{ $rightRole ?? 'Company Representative' }}</p>
            <p class="pdf-sign-date">Date: ____________________</p>
        </div>
    </div>
</section>

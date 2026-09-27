<?php

test('application timezone is Asia/Yangon', function () {
    expect(config('app.timezone'))->toBe('Asia/Yangon');
    expect(now()->timezoneName)->toBe('Asia/Yangon');
    expect(now()->utcOffset())->toBe(390); // UTC+06:30 in minutes
});

test('payment_date uses application timezone calendar day', function () {
    $frozen = \Illuminate\Support\Carbon::parse('2026-09-25 15:19:00', 'Asia/Yangon');
    \Illuminate\Support\Carbon::setTestNow($frozen);

    expect(now()->toDateString())->toBe('2026-09-25')
        ->and(now()->format('Y-m-d H:i'))->toBe('2026-09-25 15:19');

    \Illuminate\Support\Carbon::setTestNow();
});

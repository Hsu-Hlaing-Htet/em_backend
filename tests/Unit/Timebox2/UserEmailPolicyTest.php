<?php

use App\Rules\AllowedUserEmail;
use App\Support\UserEmail;
use Illuminate\Support\Facades\Validator;

test('account email format accepts providers beyond Gmail without database access', function (string $email): void {
    // Uniqueness and reserved ownership have separate database-backed feature coverage.
    $rules = array_filter(UserEmail::createRules(), fn ($rule) => ! $rule instanceof Closure);
    $validator = Validator::make(['email' => UserEmail::normalize($email)], ['email' => $rules]);

    expect($validator->passes())->toBeTrue();

    $errors = [];
    AllowedUserEmail::forUpdate(42, 'old@gmail.com')->validate('email', $email, function ($message) use (&$errors): void {
        $errors[] = $message;
    });
    expect($errors)->toBeEmpty();
})->with(['user@gmail.com', 'user@yahoo.com', 'user@outlook.com', 'user@example.org', ' USER@EXAMPLE.ORG ']);

test('account email format still rejects missing or malformed addresses', function (string $email): void {
    $rules = array_filter(UserEmail::createRules(), fn ($rule) => ! $rule instanceof Closure);
    $validator = Validator::make(['email' => $email], ['email' => $rules], UserEmail::messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
})->with(['', 'not-an-email', 'user@', '@example.org']);

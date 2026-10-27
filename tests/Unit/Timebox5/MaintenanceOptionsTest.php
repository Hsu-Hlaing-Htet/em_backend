<?php

use App\Support\MaintenanceRequestOptions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

test('new maintenance categories require active status but historical edits retain inactive categories', function (): void {
    expect((string) MaintenanceRequestOptions::activeCategoryRule())
        ->toBe('exists:maintenance_categories,slug,status,"active"')
        ->and((string) MaintenanceRequestOptions::existingCategoryRule())
        ->toBe('exists:maintenance_categories,slug');
});

test('maintenance priority validation accepts supported values and rejects unknown severity', function (string $priority, bool $valid): void {
    $validator = Validator::make(['priority' => $priority], [
        'priority' => ['required', Rule::in(MaintenanceRequestOptions::PRIORITIES)],
    ]);
    expect($validator->passes())->toBe($valid);
})->with([
    ['low', true], ['medium', true], ['high', true], ['critical', false], ['', false],
]);

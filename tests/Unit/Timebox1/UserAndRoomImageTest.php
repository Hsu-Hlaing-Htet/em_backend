<?php

use App\Models\Role;
use App\Models\User;
use App\Policies\BuildingPolicy;
use App\Services\RoomImageService;
use Illuminate\Support\Facades\Hash;

test('user role helpers reflect the loaded role', function () {
    $user = new User;
    $user->setRelation('role', new Role(['name' => Role::ADMIN]));

    expect($user->hasRole(Role::ADMIN))->toBeTrue()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->isCustomer())->toBeFalse();
});

test('user passwords are hashed through the model cast', function () {
    $user = new User;
    $user->password = 'secret-password';

    expect($user->password)
        ->not->toBe('secret-password')
        ->and(Hash::check('secret-password', $user->password))
        ->toBeTrue();
});

test('building policy grants admin roles and denies customers', function () {
    $policy = new BuildingPolicy;
    $building = new \App\Models\Building;
    $admin = new User;
    $admin->setRelation('role', new Role(['name' => Role::ADMIN]));
    $customer = new User;
    $customer->setRelation('role', new Role(['name' => Role::CUSTOMER]));

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        $arguments = $ability === 'viewAny' || $ability === 'create'
            ? [$admin]
            : [$admin, $building];
        expect($policy->{$ability}(...$arguments))->toBeTrue();

        $arguments[0] = $customer;
        expect($policy->{$ability}(...$arguments))->toBeFalse();
    }
});

test('room image paths are normalized from storage URLs', function () {
    $service = new RoomImageService;

    expect($service->normalizeImagePath('/storage/rooms/lobby.jpg'))
        ->toBe('rooms/lobby.jpg')
        ->and($service->normalizeImagePath('storage/rooms/lobby.jpg'))
        ->toBe('rooms/lobby.jpg')
        ->and($service->normalizeImagePath(' /rooms/lobby.jpg '))
        ->toBe('rooms/lobby.jpg')
        ->and($service->normalizeImagePath(null))
        ->toBeNull();
});

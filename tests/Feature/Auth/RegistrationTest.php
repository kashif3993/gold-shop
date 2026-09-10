<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', ['username' => 'testuser', 'email' => 'test@example.com']);
});

test('registration does not accept a full name', function () {
    $this->post('/register', [
        'username' => 'noname',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    // full_name is no longer a column or a field
    expect(\Illuminate\Support\Facades\Schema::hasColumn('users', 'full_name'))->toBeFalse();
});

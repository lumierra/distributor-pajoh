<?php

use App\Rules\PasswordPolicy;
use Illuminate\Support\Facades\Validator;

function runPolicy(string $password, ?string $forbidden = null): array
{
    $validator = Validator::make(
        ['password' => $password],
        ['password' => [new PasswordPolicy($forbidden)]],
    );

    return $validator->errors()->get('password');
}

test('accepts a valid password with letter + digit + 8 chars', function (): void {
    expect(runPolicy('abcd1234'))->toBe([]);
});

test('rejects a password shorter than 8 chars', function (): void {
    expect(runPolicy('ab12'))->not->toBe([]);
});

test('rejects a password without letters', function (): void {
    expect(runPolicy('12345678'))->not->toBe([]);
});

test('rejects a password without digits', function (): void {
    expect(runPolicy('abcdefghij'))->not->toBe([]);
});

test('rejects a password equal (case-insensitive) to forbidden value', function (): void {
    expect(runPolicy('lumierra1', 'LUMIERRA1'))->not->toBe([]);
});

test('reports multiple violations at once', function (): void {
    $errors = runPolicy('abc');

    expect(count($errors))->toBeGreaterThanOrEqual(2);
});

<?php

test('unauthenticated root redirects to login', function (): void {
    $this->get('/')->assertRedirect(route('login'));
});

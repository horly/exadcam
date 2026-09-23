<?php

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'home' => '/',
    'views' => true,
    'lowercase_usernames' => true,
    // Fortify's login pipeline limits failed attempts to five per minute.
    'limiters' => ['login' => null],
    'redirects' => ['logout' => '/login'],
    'features' => [],
];

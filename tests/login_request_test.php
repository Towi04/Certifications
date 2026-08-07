<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

$cases = [
    [
        'input' => ['email' => 'Admin@Example.com', 'password' => 'secret123'],
        'expected' => ['identifier' => 'admin@example.com', 'password' => 'secret123'],
    ],
    [
        'input' => ['username' => 'teacher', 'password' => 'abc'],
        'expected' => ['identifier' => 'teacher', 'password' => 'abc'],
    ],
    [
        'input' => ['password' => 'xyz'],
        'expected' => ['identifier' => '', 'password' => 'xyz'],
    ],
];

foreach ($cases as $index => $case) {
    $result = Auth::resolveLoginCredentials($case['input']);
    if ($result['identifier'] !== $case['expected']['identifier'] || $result['password'] !== $case['expected']['password']) {
        fwrite(STDERR, "Fail case {$index}: " . var_export($result, true) . PHP_EOL);
        exit(1);
    }
}

echo "login request parsing ok\n";

<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null || $password === null) {
    fwrite(STDERR, "Missing worker arguments.\n");

    exit(1);
}

echo "READY\n";
fflush(STDOUT);

$kernel = $app->make(Kernel::class);

$request = Request::create(
    '/api/login',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ],
    json_encode([
        'email' => $email,
        'password' => $password,
    ], JSON_THROW_ON_ERROR)
);

$response = $kernel->handle($request);

$result = [
    'status' => $response->getStatusCode(),
    'message' => $response->getData(true)['message'] ?? null,
];

echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
fflush(STDOUT);

$kernel->terminate($request, $response);

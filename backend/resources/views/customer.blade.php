<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Super Runner</title>

    @vite([
        'resources/js/app.js',
        'resources/js/app.tsx',
    ])
</head>
<body>
    <div id="customer-app"></div>
</body>
</html>

<?php

/*
|--------------------------------------------------------------------------
| API Route Modules
|--------------------------------------------------------------------------
|
| Each module owns one API domain.
| Existing endpoints, middleware and controller actions are preserved.
|
*/

require __DIR__.'/api/auth.php';

require __DIR__.'/api/identity.php';

require __DIR__.'/api/runner.php';

require __DIR__.'/api/tasks.php';

require __DIR__.'/api/marketplace.php';

require __DIR__.'/api/profiles.php';

require __DIR__.'/api/admin.php';

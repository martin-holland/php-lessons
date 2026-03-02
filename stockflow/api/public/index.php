<?php

// 1. Load Composer's autoloader
//    This single require replaces all the manual require_once statements
//    in the original app. Composer generates a map of class names to file paths
//    so that `new SupabaseAuth()` automatically finds src/Auth/SupabaseAuth.php.
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

// 2. Load environment variables from .env
//    This replaces the manual file parsing in the original SupabaseAuth::loadEnv().
//    After this, values are available via $_ENV['SUPABASE_URL'] etc.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 3. Create the Slim application
$app = AppFactory::create();

// 4. Add built-in middleware
//    - BodyParsing: Automatically decodes JSON request bodies into arrays
//      (so you can read POST data with $request->getParsedBody() instead of
//       manually doing json_decode(file_get_contents('php://input')))
//    - ErrorMiddleware: Catches exceptions and returns proper error responses
//      (the three `true` flags enable: displayErrorDetails, logErrors, logErrorDetails)
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 5. CORS middleware
//    When React (on localhost:5173) calls the API (on localhost:8005),
//    the browser blocks the request by default — this is called "Cross-Origin
//    Resource Sharing" protection. These headers tell the browser: "it's okay,
//    I trust requests from this origin."
//
//    OPTIONS requests are "preflight" checks the browser sends before the real
//    request. We return 200 immediately so the browser proceeds.
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// 6. Load route files
//    Each file adds its own routes to $app. This keeps the entry point clean
//    while organising endpoints by feature area.
require __DIR__ . '/../src/Routes/auth.php';
require __DIR__ . '/../src/Routes/products.php';
require __DIR__ . '/../src/Routes/orders.php';
require __DIR__ . '/../src/Routes/notes.php';
require __DIR__ . '/../src/Routes/ai.php';

// 7. Start handling the request
//    Slim reads the URL and HTTP method, finds the matching route,
//    runs any middleware, calls your route function, and sends the response.
$app->run();

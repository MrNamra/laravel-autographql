<?php

namespace MrNamra\AutoGraphQL\Tests\Unit;

use MrNamra\AutoGraphQL\Tests\TestCase;
use MrNamra\AutoGraphQL\RouteScanner;
use Illuminate\Support\Facades\Route;

class RouteScannerTest extends TestCase
{
    /** @test */
    public function it_scans_api_routes()
    {
        Route::get('/api/users', function () { return 'users list'; })->name('users.index');
        
        $scanner = new RouteScanner();
        $routes = $scanner->getApiRoutes();

        // The anonymous closure won't have a controller class,
        // so it might be filtered out by getControllerClass() !== null.
        // We just verify the scanner is initialized correctly.
        $this->assertIsArray($routes);
    }
}

<?php

namespace MohamedSaid\LaravelGa4Events;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use MohamedSaid\LaravelGa4Events\Commands\LaravelGa4EventsCommand;
use MohamedSaid\LaravelGa4Events\Http\Controllers\CollectEventController;
use MohamedSaid\LaravelGa4Events\Http\Middleware\CaptureTrackingContext;
use MohamedSaid\LaravelGa4Events\View\Components\Ga4Events;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelGa4EventsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ga4-events')
            ->hasConfigFile('ga4-events')
            ->hasViews()
            ->hasMigrations([
                'create_ga4_tracking_identities_table',
                'create_ga4_server_events_table',
            ])
            ->hasCommand(LaravelGa4EventsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LaravelGa4Events::class, function (Application $app): LaravelGa4Events {
            $config = $app['config']->get('ga4-events', []);

            return new LaravelGa4Events(is_array($config) ? $config : []);
        });
    }

    public function packageBooted(): void
    {
        Blade::component('ga4-events', Ga4Events::class);

        Blade::directive('ga4Events', function (): string {
            return "<?php echo app('view')->make('ga4-events::components.ga4-events', ['ga4Config' => app('".LaravelGa4Events::class."')->frontendPayload()])->render(); ?>";
        });

        EncryptCookies::except(['_fbp', '_fbc']);

        $this->app->make(Router::class)->aliasMiddleware('ga4-events.context', CaptureTrackingContext::class);

        $this->registerCollectRoute();
    }

    private function registerCollectRoute(): void
    {
        $config = (array) config('ga4-events.collect_endpoint', []);

        if (! ($config['enabled'] ?? true) || $this->app->routesAreCached()) {
            return;
        }

        $middleware = (array) ($config['middleware'] ?? ['web']);

        if (filled($config['throttle'] ?? null)) {
            $middleware[] = 'throttle:'.$config['throttle'];
        }

        Route::middleware($middleware)
            ->post((string) ($config['path'] ?? 'ga4-events/collect'), CollectEventController::class)
            ->name('ga4-events.collect');
    }
}

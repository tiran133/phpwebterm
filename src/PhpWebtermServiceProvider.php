<?php

namespace Phpwebterm;

use Illuminate\Support\ServiceProvider;
use Phpwebterm\Console\Commands\TerminalShellWebsocket;
use Phpwebterm\Websocket\Server;
use Phpwebterm\Websocket\ServerConfig;

class PhpWebtermServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->configure();
        $this->registerServices();

    }

    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/phpwebterm.php', 'phpwebterm'
        );
    }

    protected function registerServices()
    {
        $this->app->singleton('phpwebterm', function ($app) {
            return new Server(new ServerConfig(config(('phpwebterm'))));
        });

        $this->app->alias('phpwebterm', Server::class);

    }

    public function boot(): void
    {

        $this->registerCommands();
        $this->offerPublishing();
    }

    private function registerCommands(): void
    {
        $this->commands([TerminalShellWebsocket::class]);
    }

    protected function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/phpwebterm.php' => config_path('phpwebterm.php'),
            ], 'phpwebterm-config');
        }
    }
}

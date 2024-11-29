<?php

namespace Phpwebterm\Console\Commands;

use Illuminate\Console\Command;
use Phpwebterm\Support\Phpwebterm;

class TerminalShellWebsocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'phpwebterm:terminal-shell-websocket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a websocket Server to handel the Web Terminal sessions';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {

        if (PHP_OS === 'Linux') {
            $this->addLinuxRoutes();
        } else {
            $this->addMacOSRoutes();
        }

        Phpwebterm::start();

    }

    private function addLinuxRoutes(): void
    {
        foreach (config('phpwebterm.routes.linux') as $route => $process) {
            Phpwebterm::addRoute($route, $process);
        }
    }

    private function addMacOSRoutes(): void
    {
        foreach (config('phpwebterm.routes.macos') as $route => $process) {
            Phpwebterm::addRoute($route, $process);
        }
    }
}

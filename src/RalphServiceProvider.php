<?php

namespace Collegeman\Ralph;

use Illuminate\Support\ServiceProvider;
use Collegeman\Ralph\Commands\InitCommand;
use Collegeman\Ralph\Commands\PrdCommand;
use Collegeman\Ralph\Commands\ResetCommand;
use Collegeman\Ralph\Commands\RunCommand;
use Collegeman\Ralph\Commands\StatusCommand;
use Collegeman\Ralph\Commands\SyncCommand;
use Collegeman\Ralph\GitHub\GitHubSync;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Progress\ProgressLog;

class RalphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ralph.php', 'ralph');

        $this->app->singleton(PrdManager::class, function ($app) {
            return new PrdManager(
                base_path(config('ralph.prd_path'))
            );
        });

        $this->app->singleton(ProgressLog::class, function ($app) {
            return new ProgressLog(
                base_path(config('ralph.progress_path'))
            );
        });

        $this->app->singleton(GitHubSync::class, function ($app) {
            return new GitHubSync(config('ralph.github'));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ralph.php' => config_path('ralph.php'),
            ], 'ralph-config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/ralph'),
            ], 'ralph-stubs');

            $this->commands([
                InitCommand::class,
                PrdCommand::class,
                RunCommand::class,
                StatusCommand::class,
                SyncCommand::class,
                ResetCommand::class,
            ]);
        }
    }
}

<?php

namespace Snarktank\Ralph\Commands;

use Illuminate\Console\Command;

class InitCommand extends Command
{
    protected $signature = 'ralph:init
        {--force : Overwrite existing files}';

    protected $description = 'Scaffold Ralph loop files for this project';

    public function handle(): int
    {
        $this->components->info('Initializing Ralph...');

        $this->scaffoldPrd();
        $this->scaffoldClaudeMd();
        $this->scaffoldProgressLog();
        $this->publishConfig();

        $this->newLine();
        $this->components->info('Ralph initialized! Next steps:');
        $this->line('  1. Edit prd.json with your user stories (or run <comment>php artisan ralph:prd</comment>)');
        $this->line('  2. Run <comment>php artisan ralph:run</comment> to start the loop');

        return self::SUCCESS;
    }

    private function scaffoldPrd(): void
    {
        $target = base_path(config('ralph.prd_path'));

        if (file_exists($target) && ! $this->option('force')) {
            $this->components->warn('prd.json already exists — skipping (use --force to overwrite)');

            return;
        }

        copy(__DIR__.'/../../stubs/prd.json', $target);
        $this->components->task('Created prd.json');
    }

    private function scaffoldClaudeMd(): void
    {
        $target = base_path('CLAUDE.md');
        $stub = file_get_contents(__DIR__.'/../../stubs/CLAUDE.md');

        if (file_exists($target)) {
            $existing = file_get_contents($target);

            if (str_contains($existing, '# Ralph Agent Instructions')) {
                $this->components->warn('CLAUDE.md already contains Ralph instructions — skipping');

                return;
            }

            file_put_contents($target, $existing."\n\n".$stub);
            $this->components->task('Appended Ralph instructions to CLAUDE.md');

            return;
        }

        file_put_contents($target, $stub);
        $this->components->task('Created CLAUDE.md');
    }

    private function scaffoldProgressLog(): void
    {
        $target = base_path(config('ralph.progress_path'));

        if (file_exists($target) && ! $this->option('force')) {
            $this->components->warn('progress.txt already exists — skipping');

            return;
        }

        $header = "# Ralph Progress Log\n";
        $header .= '# Created: '.date('Y-m-d H:i:s')."\n";
        $header .= "# This file is append-only. Do not delete entries.\n";

        file_put_contents($target, $header);
        $this->components->task('Created progress.txt');
    }

    private function publishConfig(): void
    {
        if (file_exists(config_path('ralph.php'))) {
            $this->components->warn('config/ralph.php already published — skipping');

            return;
        }

        $this->call('vendor:publish', ['--tag' => 'ralph-config']);
    }
}

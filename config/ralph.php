<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Paths
    |--------------------------------------------------------------------------
    |
    | Paths relative to the project root where Ralph stores its state.
    |
    */

    'prd_path' => 'prd.json',
    'progress_path' => 'progress.txt',

    /*
    |--------------------------------------------------------------------------
    | Iteration Limits
    |--------------------------------------------------------------------------
    */

    'max_iterations' => 10,
    'sleep_between_iterations' => 2,

    /*
    |--------------------------------------------------------------------------
    | Claude Code Settings
    |--------------------------------------------------------------------------
    */

    'claude' => [
        'binary' => 'claude',
        'model' => null,
        'max_turns' => null,
        'permission_mode' => 'bypassPermissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Gates
    |--------------------------------------------------------------------------
    |
    | Commands run after each story implementation. Each gate must exit 0
    | to pass. If a gate has a 'fallback' key, that command is tried when
    | the primary binary is not found.
    |
    */

    'gates' => [
        'phpstan' => [
            'command' => './vendor/bin/phpstan analyse --no-progress --error-format=raw',
            'label' => 'PHPStan',
            'enabled' => true,
        ],
        'tests' => [
            'command' => './vendor/bin/pest --no-interaction',
            'fallback' => './vendor/bin/phpunit --no-interaction',
            'label' => 'Tests',
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Completion Signal
    |--------------------------------------------------------------------------
    |
    | String that must appear in Claude's output to signal all stories are done.
    |
    */

    'completion_signal' => '<promise>COMPLETE</promise>',

    /*
    |--------------------------------------------------------------------------
    | GitHub Integration
    |--------------------------------------------------------------------------
    |
    | Bidirectional sync between prd.json stories and GitHub Issues.
    | Requires the `gh` CLI to be installed and authenticated.
    |
    */

    'github' => [
        'enabled' => false,
        'label' => 'ralph',
        'close_on_pass' => true,
        'comment_on_progress' => true,
    ],

];

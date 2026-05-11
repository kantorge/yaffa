<?php

// config for JoshEmbling/ArtisanBrowse
return [
    // Add any Artisan commands you want to exclude from the browse list here.
    'blacklist_commands' => [
        // Example: 'horizon', 'octane', 'sail'
    ],

    // Number of artisan commands to show before enabling scroll in the command selection UI.
    'select_command_scroll' => 50,

    // Number of options to show before enabling scroll in the argument/option selection UI.
    'select_options_scroll' => 20,

    // Options/arguments to skip when collecting input for a command.
    'skip_options' => ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env', 'silent'],

    // Whether to show the command preview before confirming
    'show_command_preview' => true,

    // Whether to search against command descriptions
    'search_descriptions' => true,

    // Whether to automatically execute the command without showing the confirmation prompt
    'auto_execute' => false,
];

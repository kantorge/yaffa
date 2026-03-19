<?php

namespace Deployer;

use Throwable;

require 'recipe/laravel.php';
require 'contrib/cachetool.php';

set('repository', 'https://github.com/kantorge/yaffa.git');
set('keep_releases', 5);
set('identity_file', '~/.ssh/private_key');
set('branch', getenv('DEPLOY_REF') ?: 'main');

set('upload_dirs', ['public/build']);

set('cachetool', static function () {
    $commonSockets = [
        '/run/php/php8.4-fpm.sock',
        '/var/run/php/php8.4-fpm.sock',
        '/run/php/php-fpm.sock',
        '/var/run/php/php-fpm.sock',
    ];

    foreach ($commonSockets as $socketPath) {
        if (test('[ -S ' . escapeshellarg($socketPath) . ' ]')) {
            return $socketPath;
        }
    }

    return '';
});

set('cachetool_args', '');

task('build:assets', static function () {
    runLocally('npm ci');
    runLocally('npm run build');
});

task('deploy:upload_assets', static function () {
    upload('public/build/', '{{ release_path }}/public/build');
});

task('deploy:reset_opcache', static function () {
    if (get('cachetool') === '' && get('cachetool_args') === '') {
        warning('Skipping OPcache reset. Set cachetool or cachetool_args in deploy.php to enable cachetool.');
        return;
    }

    try {
        invoke('cachetool:clear:opcache');
    } catch (Throwable $throwable) {
        warning('OPcache reset failed: ' . $throwable->getMessage());
    }
});

before('deploy:prepare', 'build:assets');
after('deploy:vendors', 'deploy:upload_assets');
after('deploy:symlink', 'deploy:reset_opcache');

host('private')
    ->set('hostname', getenv('SSH_HOST'))
    ->set('remote_user', getenv('SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'));

desc('Deploy YAFFA to host defined by environment variables');
task('yaffa', [
    'deploy'
]);

after('deploy:publish', 'artisan:queue:restart');

after('deploy:failed', 'deploy:unlock');

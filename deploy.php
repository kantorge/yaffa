<?php

namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'https://github.com/kantorge/yaffa.git');
set('keep_releases', 5);
set('identity_file', '~/.ssh/private_key');
set('branch', getenv('DEPLOY_REF') ?: 'main');

set('upload_dirs', ['public/build']);

task('build:assets', static function () {
    runLocally('npm ci');
    runLocally('npm run build');
});

task('deploy:upload_assets', static function () {
    upload('public/build/', '{{ release_path }}/public/build');
});

before('deploy:prepare', 'build:assets');
after('deploy:vendors', 'deploy:upload_assets');

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

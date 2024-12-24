<?php

namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'https://github.com/kantorge/yaffa.git');
set('keep_releases', 5);
set('identity_file', '~/.ssh/private_key');
set('branch', 'main');

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

<?php

// Intentionally empty. Laravel 12's `LoadConfiguration` merges this file over the
// framework's own default `auth.php` (`defaults.guard=web`, `guards.web` session/eloquent,
// `providers.users`, `passwords.users` all come from there - verified via
// `artisan config:show auth`), and Sanctum's own service provider injects `guards.sanctum`
// regardless of this file's contents. The only thing this file used to add was a legacy
// `guards.api` entry (driver `token`) from pre-Sanctum API auth, which nothing in the app
// references (`auth:api` does not appear anywhere) and was removed as dead config when
// Sanctum became the only API auth mechanism. Do not repeat the framework defaults here -
// that would just be an app-level copy that can silently drift from the framework's own.
return [];

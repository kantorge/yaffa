# Upgrade guide for major versions

This document describes the breaking changes of major versions, and the steps that need to be performed to get you migrated.

Table of contents:

- [Upgrade from YAFFA 1.x to 2.x](#upgrade-from-yaffa-1x-to-2x)

## Upgrade from YAFFA 1.x to 2.x

The main reason for increasing the version is the migration of the framework from Laravel 10 to Laravel 12.

### Breaking Changes

- Some of the environment variable names used by YAFFA were changed, and you need to update them as part of your migration.

### Step-by-step Guide

#### 1. Update your `.env` file with the following changes

- The broadcast driver key has been renamed. As YAFFA is not using this Laravel feature, the actual impact is minimal.

```diff
- BROADCAST_DRIVER=#your_value#
+ BROADCAST_CONNECTION=#your_value#
```

- The key for the cache driver has been renamed. Some YAFFA features rely on caching so you need to make this change.

```diff
- CACHE_DRIVER=#your_value#
+ CACHE_STORE=#your_value#
```

- The mail encryption environment variable has beeen renamed to be more generic, as not all mail schemes are encryption.

```diff
- MAIL_ENCRYPTION=#your_value#
+ MAIL_SCHEME=#your_value#
```

- Add the following keys and default values that were introduced by Laravel 11. Customize them, if needed.

```diff
-
+ LOG_STACK=single
+ SESSION_ENCRYPT=false
+ SESSION_PATH=/
+ APP_MAINTENANCE_DRIVER=file
+ APP_MAINTENANCE_STORE=database
+ BCRYPT_ROUNDS=12
```

- Even though the language of the UI is controlled by user preferences, Laravel 11 introduced some environment variables related to locale, which should be added as default values for the application

```diff
-
+ APP_LOCALE=en
+ APP_FALLBACK_LOCALE=en
+ APP_FAKER_LOCALE=en_US
```

- Laravel Telescope is now installed in production, but disabled by default. Verify that the `TELESCOPE_ENABLED` flag is in a state as you need it.

#### 2. Run the actual update steps per your hosting option

- Update the code base from Packagist or GitHub. Make sure to install updated dependencies, run migrations, and clear cached assets.
- Pull the latest YAFFA image from Docker Hub, and restart your container. The entrypoint will take care of running the migrations and clearing various caches.

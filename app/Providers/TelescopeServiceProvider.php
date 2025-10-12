<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException() ||
                $entry->isFailedRequest() ||
                $entry->type === EntryType::JOB || // Keep all jobs
                $entry->isSlowQuery() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag();
        });

        Telescope::filterBatch(function (Collection $entries) {
            if ($this->app->environment('local')) {
                return $entries;
            }

            return $entries->filter(function (IncomingEntry $entry) {
                return $entry->isReportableException() ||
                    $entry->isFailedRequest() ||
                    $entry->type === EntryType::JOB || // Keep all jobs
                    $entry->isSlowQuery() ||
                    $entry->isScheduledTask() ||
                    $entry->hasMonitoredTag();
            });
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails()
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate()
    {
        Gate::define(
            'viewTelescope',
            fn($user) => in_array($user->email, [
                config('yaffa.admin_email'),
            ])
        );
    }
}

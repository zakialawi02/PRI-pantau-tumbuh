<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            // Di local → simpan semua
            if ($this->app->environment('local')) {
                return true;
            }

            // Di production → simpan hanya yang penting
            return $entry->isReportableException()   // Exception
                || $entry->isFailedRequest()        // Request gagal (status 500, dll.)
                || $entry->isFailedJob()            // Job gagal
                || $entry->isScheduledTask()        // Task scheduler
                // || $entry->type === 'request'       // Semua HTTP request
                || $entry->type === 'log';          // Log manual (\Log::info, dll.)
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
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
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return $user->role === 'superadmin';
        });
    }
}

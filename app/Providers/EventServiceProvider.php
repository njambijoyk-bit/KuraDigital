<?php

namespace App\Providers;

use App\Events\IncidentReported;
use App\Events\TallyResultSubmitted;
use App\Events\TallyResultVerified;
use App\Listeners\RecordElectionDayActivity;
use App\Models\Donation;
use App\Models\Expense;
use App\Observers\DonationObserver;
use App\Observers\ExpenseObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        TallyResultSubmitted::class => [
            RecordElectionDayActivity::class,
        ],
        TallyResultVerified::class => [
            RecordElectionDayActivity::class,
        ],
        IncidentReported::class => [
            RecordElectionDayActivity::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Expense::observe(ExpenseObserver::class);
        Donation::observe(DonationObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

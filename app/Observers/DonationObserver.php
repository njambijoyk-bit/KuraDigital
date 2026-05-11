<?php

namespace App\Observers;

use App\Models\Donation;
use App\Services\AutoPostingService;

class DonationObserver
{
    public function created(Donation $donation): void
    {
        if ($donation->status === 'completed') {
            app(AutoPostingService::class)->postDonation($donation);
        }
    }

    public function updated(Donation $donation): void
    {
        if (!$donation->wasChanged('status')) {
            return;
        }

        $autoPosting = app(AutoPostingService::class);

        if ($donation->status === 'completed') {
            $autoPosting->postDonation($donation);
        }

        if ($donation->getOriginal('status') === 'completed' && in_array($donation->status, ['reversed', 'failed'])) {
            $autoPosting->reverseDonation($donation);
        }
    }
}

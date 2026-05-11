<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\AutoPostingService;

class ExpenseObserver
{
    public function updated(Expense $expense): void
    {
        if (!$expense->wasChanged('status')) {
            return;
        }

        $autoPosting = app(AutoPostingService::class);

        if ($expense->status === 'approved') {
            $autoPosting->postExpenseApproval($expense);
        }

        if ($expense->getOriginal('status') === 'approved' && $expense->status === 'rejected') {
            $autoPosting->reverseExpense($expense, $expense->approved_by);
        }
    }
}

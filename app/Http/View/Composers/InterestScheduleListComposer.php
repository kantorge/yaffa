<?php

namespace App\Http\View\Composers;

use App\Models\Investment;
use Illuminate\View\View;

class InterestScheduleListComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $allInterestSchedules = Investment::getInterestSchedules();
        $view->with('allInterestSchedules', $allInterestSchedules);
    }
}
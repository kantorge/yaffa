<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ImportController extends Controller
{
    /**
     * Display UI for importing and parsing QIF files.
     *
     * @return View
     */
    public function importQif(): View
    {
        /**
         * @get('/import/qif')
         * @name('import.qif')
         * @middlewares('web')
         */
        return view('import.qif');
    }
}

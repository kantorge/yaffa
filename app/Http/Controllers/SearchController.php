<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Search various models based on the search term.
     *
     * @param  Request  $request
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        $searchTerm = $request->get('q');
        $results = [];

        if ($searchTerm && mb_strlen($searchTerm) > 2) {
            // Search for accounts of the user
            $results['accounts'] = $this->searchAccounts($searchTerm);

            // Search payees of the user
            $results['payees'] = $this->searchPayees($searchTerm);

            // Search tags of the user
            $results['tags'] = $this->searchTags($searchTerm);

            // Search categories of the user
            $results['categories'] = $this->searchCategories($searchTerm);

            // Search investments of the user
            $results['investments'] = $this->searchInvestments($searchTerm);

            // Search transactions of the user for transaction or transaction item comments
            $results['transactions'] = $this->searchTransactions($searchTerm);
        }

        return view('search.search', compact('results', 'searchTerm'));
    }

    /**
     * Search for accounts of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchAccounts($searchTerm)
    {
        return Auth::user()
            ->accounts()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('name');
    }

    /**
     * Search for payees of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchPayees($searchTerm)
    {
        return Auth::user()
            ->payees()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('name');
    }

    /**
     * Search for tags of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchTags($searchTerm)
    {
        return Auth::user()
            ->tags()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('name');
    }

    /**
     * Search for categories of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchCategories($searchTerm)
    {
        return Auth::user()
            ->categories()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('full_name');
    }

    /**
     * Search for investments of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchInvestments($searchTerm)
    {
        return Auth::user()
            ->investments()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('name');
    }

    /**
     * Search for transactions of the user.
     *
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function searchTransactions($searchTerm)
    {
        return Auth::user()
            ->transactions()
            ->byScheduleType('none')
            ->whereRaw('UPPER(`comment`) LIKE ?', ['%'.strtoupper($searchTerm).'%'])
            ->get()
            ->sortBy('date');
    }
}

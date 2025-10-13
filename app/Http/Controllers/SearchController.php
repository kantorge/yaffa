<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    /**
     * Search various models based on the search term.
     *
     * @param Request $request
     * @return View
     */
    public function search(Request $request): View
    {
        /**
         * @get('/search')
         * @name('search')
         * @middlewares('web')
         */
        $searchTerm = $request->get('q');
        $results = [];

        if (!($searchTerm && mb_strlen($searchTerm) > 2)) {
            return view('search.search', compact('results', 'searchTerm'));
        }

        $user = $request->user();

        // Search for accounts of the user
        $results['accounts'] = $this->searchAccounts($user, $searchTerm);

        // Search payees of the user
        $results['payees'] = $this->searchPayees($user, $searchTerm);

        // Search tags of the user
        $results['tags'] = $this->searchTags($user, $searchTerm);

        // Search categories of the user
        $results['categories'] = $this->searchCategories($user, $searchTerm);

        // Search investments of the user
        $results['investments'] = $this->searchInvestments($user, $searchTerm);

        // Search transactions of the user for transaction or transaction item comments
        $results['transactions'] = $this->searchTransactions($user, $searchTerm);

        return view('search.search', compact('results', 'searchTerm'));
    }

    /**
     * Search for accounts of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchAccounts(User $user, string $searchTerm): Collection
    {
        return $user
            ->accounts()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Search for payees of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchPayees(User $user, string $searchTerm): Collection
    {
        return $user
            ->payees()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Search for tag of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchTags(User $user, string $searchTerm): Collection
    {
        return $user
            ->tags()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Search for categories of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchCategories(User $user, string $searchTerm): Collection
    {
        return $user
            ->categories()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->get()
            ->sortBy('full_name');
    }

    /**
     * Search for investments of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchInvestments(User $user, string $searchTerm): Collection
    {
        return $user
            ->investments()
            ->whereRaw('UPPER(`name`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Search for transactions of the user.
     *
     * @param User $user
     * @param string $searchTerm
     * @return Collection
     */
    private function searchTransactions(User $user, string $searchTerm): Collection
    {
        return $user
            ->transactions()
            ->with('transactionType')
            ->whereRaw('UPPER(`comment`) LIKE ?', ['%' . mb_strtoupper($searchTerm) . '%'])
            ->orderBy('date')
            ->get();
    }
}

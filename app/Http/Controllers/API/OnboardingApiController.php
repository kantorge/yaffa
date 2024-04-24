<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Onboard\Facades\Onboard;

class OnboardingApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function getOnboardingData(Request $request, string $topic): JsonResponse
    {
        $this->loadOnboardingSteps($topic);

        return response()->json(
            [
                'dismissed' => $request->user()->hasFlag('dismissOnboardingWidget' . ucfirst($topic)),
                'steps' => $request->user()->onboarding()->steps(),
            ],
            Response::HTTP_OK
        );
    }

    public function setDismissedFlag(Request $request, string $topic): Response
    {
        $request->user()->flag('dismissOnboardingWidget' . ucfirst($topic));

        return response('', Response::HTTP_OK);
    }

    public function setCompletedTourFlag(Request $request, string $topic): Response
    {
        $request->user()->flag('viewProductTour-' . $topic);

        return response('', Response::HTTP_OK);
    }

    /**
     * @param string $topic
     * @uses onboardingTopicDataDashboard
     * @uses onboardingTopicDataReportsSchedules
     * @uses onboardingTopicDataAccountGroups
     * @uses onboardingTopicDataInvestmentGroups
     */
    private function loadOnboardingSteps(string $topic): void
    {
        $this->{'onboardingTopicData' . ucfirst($topic)}();
    }

    private function onboardingTopicDataDashboard(): void
    {
        Onboard::addStep(__('Have at least one currency added'))
            ->link(route('currency.create'))
            ->cta(__('Add a currency'))
            ->completeIf(fn (User $model) => Currency::whereUserId($model->id)->count() > 0);

        Onboard::addStep(__('Have your base currency set'))
            ->link(route('currency.index'))
            ->cta(__('Review currency settings'))
            ->completeIf(fn (User $model) => $model->baseCurrency() !== null);

        Onboard::addStep(__('Have at least one account group added'))
            ->link(route('account-group.create'))
            ->cta(__('Add an account group'))
            ->completeIf(fn (User $model) => AccountGroup::whereUserId($model->id)->count() > 0);

        Onboard::addStep(__('Have at least one account added'))
            ->link(route('account-entity.create', ['type' => 'account']))
            ->cta(__('Add an account'))
            ->completeIf(fn (User $model) => AccountEntity::whereUserId($model->id)->accounts()->count() > 0);

        Onboard::addStep(__('Have some payees added'))
            ->link(route('account-entity.create', ['type' => 'payee']))
            ->cta(__('Add a payee'))
            ->completeIf(fn (User $model) => AccountEntity::whereUserId($model->id)->payees()->count() > 0);

        Onboard::addStep(__('Have some categories added'))
            ->link(route('categories.create'))
            ->cta(__('Add a category'))
            ->completeIf(fn (User $model) => Category::whereUserId($model->id)->count() > 0);

        Onboard::addStep(__('Add your first transaction'))
            ->link(route('transaction.create', ['type' => 'standard']))
            ->cta(__('Add transaction'))
            ->completeIf(fn (User $model) => $model->transactionCount() > 0);
    }

    private function onboardingTopicDataReportsSchedules(): void
    {
        Onboard::addStep(__('View the guided tour for this page'))
            ->attributes([
                'tour' => true,
                'icon' => 'fa fa-fw fa-info',
            ])
            ->completeIf(fn (User $model) => $model->hasFlag('viewProductTour-ReportsSchedules'));
    }

    private function onboardingTopicDataAccountGroups(): void
    {
        Onboard::addStep(__('View the guided tour for this page'))
            ->attributes([
                'tour' => true,
                'icon' => 'fa fa-fw fa-info',
            ])
            ->completeIf(fn (User $model) => $model->hasFlag('viewProductTour-AccountGroups'));
    }

    private function onboardingTopicDataInvestmentGroups(): void
    {
        Onboard::addStep(__('View the guided tour for this page'))
            ->attributes([
                'tour' => true,
                'icon' => 'fa fa-fw fa-info',
            ])
            ->completeIf(fn (User $model) => $model->hasFlag('viewProductTour-InvestmentGroups'));
    }
}

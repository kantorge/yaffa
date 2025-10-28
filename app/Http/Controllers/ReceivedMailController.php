<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\ReceivedMail;
use App\Services\ReceivedMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class ReceivedMailController extends Controller implements HasMiddleware
{
    protected ReceivedMailService $receivedMailService;

    public function __construct(ReceivedMailService $receivedMailService)
    {

        $this->receivedMailService = $receivedMailService;
    }

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\ReceivedMail', only: ['index']),
            new Middleware('can:view,received_mail', only: ['show']),
            new Middleware('can:create,App\Models\ReceivedMail', only: ['create', 'store']),
            new Middleware('can:update,received_mail', only: ['edit', 'update']),
            new Middleware('can:delete,received_mail', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $mails = ReceivedMail::where('user_id', auth()->id())
            ->get();

        JavaScriptFacade::put([
            'mails' => $mails,
        ]);

        return view('received-mail.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReceivedMail $receivedMail): View
    {
        JavaScriptFacade::put([
            'mail' => $receivedMail,
        ]);

        return view('received-mail.show', compact('receivedMail'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReceivedMail $receivedMail): RedirectResponse
    {
        $result = $this->receivedMailService->delete($receivedMail);

        if ($result['success']) {
            self::addSimpleSuccessMessage(__('Email deleted'));
            return redirect()->route('received-mail.index');
        }

        self::addSimpleErrorMessage($result['error']);
        return redirect()->back();
    }
}

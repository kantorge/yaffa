<?php

namespace App\Http\Controllers;

use App\Models\ReceivedMail;
use App\Services\ReceivedMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class ReceivedMailController extends Controller
{
    protected ReceivedMailService $receivedMailService;

    public function __construct(ReceivedMailService $receivedMailService)
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('can:viewAny,App\Models\ReceivedMail')->only('index');
        $this->middleware('can:view,received_mail')->only('show');
        $this->middleware('can:create,App\Models\ReceivedMail')->only('create', 'store');
        $this->middleware('can:update,received_mail')->only('edit', 'update');
        $this->middleware('can:delete,received_mail')->only('destroy');
        $this->receivedMailService = $receivedMailService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
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
     *
     * @param ReceivedMail $receivedMail
     * @return View
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
     *
     * @param ReceivedMail $receivedMail
     * @return RedirectResponse
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

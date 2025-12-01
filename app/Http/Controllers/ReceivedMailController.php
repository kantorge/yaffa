<?php

namespace App\Http\Controllers;

use App\Events\IncomingEmailReceived;
use App\Models\ReceivedMail;
use App\Services\ReceivedMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Smalot\PdfParser\Parser as PdfParser;

class ReceivedMailController extends Controller
{
    protected ReceivedMailService $receivedMailService;

    public function __construct(ReceivedMailService $receivedMailService)
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(ReceivedMail::class);
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

    /**
     * Upload and process a PDF document
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function uploadPdf(Request $request): RedirectResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('pdf');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('pdfs', $filename, 'local');

            // Extract text from PDF
            $parser = new PdfParser();
            $pdf = $parser->parseFile(storage_path('app/' . $path));
            $text = $pdf->getText();

            // Create received mail record
            $receivedMail = ReceivedMail::create([
                'message_id' => 'pdf-' . time() . '-' . auth()->id(),
                'user_id' => auth()->id(),
                'subject' => 'Landlord Statement: ' . $file->getClientOriginalName(),
                'html' => '<p>PDF Document: ' . $file->getClientOriginalName() . '</p>',
                'text' => "From: Uploaded PDF\n\n" . $text,
                'processed' => false,
                'handled' => false,
            ]);

            // Fire event to process the PDF
            event(new IncomingEmailReceived($receivedMail));

            self::addSimpleSuccessMessage(__('PDF uploaded successfully. Processing...'));
            return redirect()->route('received-mail.show', $receivedMail);

        } catch (\Exception $e) {
            self::addSimpleErrorMessage(__('Error uploading PDF: ') . $e->getMessage());
            return redirect()->back();
        }
    }
}

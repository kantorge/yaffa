<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingEmailByAi;
use App\Models\ReceivedMail;
use App\Services\ReceivedMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceivedMailApiController extends Controller implements HasMiddleware
{
    protected ReceivedMailService $receivedMailService;

    public function __construct()
    {

        $this->receivedMailService = new ReceivedMailService();
    }

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * Reset the processed status of the given received mail.
     */
    public function resetProcessed(ReceivedMail $receivedMail): JsonResponse
    {
        /**
         * @get('/api/received-mail/{receivedMail}/reset-processed')
         * @name('api.received-mail.reset-processed')
         * @middlewares('web', 'auth', 'verified')
         */
        $result = $this->receivedMailService->resetProcessed($receivedMail);

        if ($result['success']) {
            // Dispatch the job to process the received mail.
            ProcessIncomingEmailByAi::dispatch($receivedMail);

            return response()
                ->json(
                    ['receivedMail' => $receivedMail],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'receivedMail' => $receivedMail,
                    'error' => $result['error'],
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
    }

    /**
     * Remove the specified investment.
     */
    public function destroy(ReceivedMail $receivedMail): JsonResponse
    {
        /**
         * @delete('/api/received-mail/{receivedMail}')
         * @name('api.received-mail.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
        $result = $this->receivedMailService->delete($receivedMail);

        if ($result['success']) {
            return response()
                ->json(
                    ['receivedMail' => $receivedMail],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'receivedMail' => $receivedMail,
                    'error' => $result['error'],
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
    }
}

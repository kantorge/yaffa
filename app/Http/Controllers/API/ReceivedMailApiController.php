<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReceivedMail;
use App\Services\ReceivedMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceivedMailApiController extends Controller
{
    protected ReceivedMailService $receivedMailService;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);

        $this->receivedMailService = new ReceivedMailService();
    }

    /**
     * Remove the specified investment.
     *
     * @param ReceivedMail $receivedMail
     * @return JsonResponse
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

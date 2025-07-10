<?php

namespace App\Http\Controllers;

use App\Models\EmailVerification;
use App\Services\EmailVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\StoreRequest\StoreEmailVerificationRequest;
use App\Http\Requests\UpdateRequest\UpdateEmailVerificationRequest;
use Illuminate\Http\JsonResponse;


class EmailVerificationController extends Controller
{
    /**
     * @var EmailVerificationService
     */
    private EmailVerificationService $service;

    /**
     * @param EmailVerificationService $service
     */
    public function __construct(EmailVerificationService $service)
    {
        $this->service = $service;
    }

    /**
     * @param StoreEmailVerificationRequest $request
     * @return array|Builder|Collection|EmailVerification
     * @throws \Throwable
     */
    public function sendEmailVerification(StoreEmailVerificationRequest $request): array|Builder|Collection|EmailVerification
    {
        return $this->service->createModel($request->validated());
    }

    /**
     * @param UpdateEmailVerificationRequest $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function checkEmailVerification(UpdateEmailVerificationRequest $request): JsonResponse
    {
        return $this->service->checkVerificationCode($request->validated());
    }
}

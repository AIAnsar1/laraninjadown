<?php

namespace App\Services;

use App\Events\EmailVerificationEvent;
use App\Jobs\EmailVerificaitonJob;
use App\Repositories\EmailVerificationRepository;
use Illuminate\Http\JsonResponse;
use Throwable;
use App\Dtos\ApiResponse;
use App\Mail\VerificationMail;
use Illuminate\Support\Facades\{Hash, Mail};
use Illuminate\Database\Eloquent\{Model, Builder, Collection};
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationService extends BaseService
{
    /**
     * @param EmailVerificationRepository $repository
     */
    public function __construct(EmailVerificationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param $data
     * @return Model|Model[]|Builder|Builder[]|Collection|null
     * @throws Throwable
     */
    public function createModel(array $data): array|Collection|Builder|Model|null
    {
        $randomCode = random_int(100000, 999999);
        $mailMessage = [
            'to' => $data['email'],
            'subject' => 'Email Verification Code',
            'code' => $randomCode,
        ];

        // $isSending = Mail::send(new VerificationMail($mailMessage));
        // $isSending = EmailVerificationEvent::dispatch(new VerificationMail($mailMessage));
        // $isSending = event(new VerificationMail($mailMessage));
        $isSending = event(new EmailVerificationEvent($mailMessage));
        // $isSending = EmailVerificationJob::dispatch(new VerificationMail($mailMessage));

        if ($isSending)
        {
            $data = [
                'email' => $data['email'],
                'code' => bcrypt($randomCode),
            ];
        }
        return $this->repository->create($data);
    }

    /**
     * checkEmailVerificationCode
     *
     * @param array $data
     * @return JsonResponse
     * @throws Throwable
     */
    public function checkVerificationCode(array $data): JsonResponse
    {
        $model = $this->repository->findByEmail($data['email']);
        if ($model and Hash::check($data['code'], $model->code))
        {
            return ApiResponse::success([
                'email' => $data['email']
            ]);
        } else {
            return ApiResponse::error("The provided code  is incorrect.", Response::HTTP_UNAUTHORIZED);
        }

    }
}

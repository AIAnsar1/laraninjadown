<?php

namespace App\Services\Auth;

use App\Services\BaseService;
use App\Repositories\EmailVerificationRepository;
use App\Dtos\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use App\Models\User;
use App\Repositories\UserRepository;


class AuthService extends BaseService
{
    private $EmailVerificationCodeRepository;
    private $UserRepository;

    public function __construct(UserRepository $UserRepository, EmailVerificationRepository $EmailVerificationCodeRepository)
    {
        $this->UserRepository = $UserRepository;
        $this->EmailVerificationCodeRepository = $EmailVerificationCodeRepository;
    }

    /**
     * @param array $data
     * @return JsonResponse
     * @throws Throwable
     */
    public function login(array $data): JsonResponse
    {
        /**
         * @var $model User
         */

        $model = $this->UserRepository->findByEmailOrName($data['email']);

        if ($model and Hash::check($data['password'], $model->password))
        {
            return ApiResponse::success([
                'type' => 'Bearer',
                'token' => $this->UserRepository->createToken($data['email']),
                'user' => $model,
            ]);
        } else {
            return ApiResponse::error("The provided username or password is incorrect.", Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @param array|string $data
     * @return JsonResponse
     * @throws Throwable
     */
    public function register(array|string $data): JsonResponse
    {
        $EmailVerificationRepository = $this->EmailVerificationCodeRepository->findByEmail($data['email']);

        if ($EmailVerificationRepository and Hash::check($data['code'], $EmailVerificationRepository->code))
        {
            $data['password'] = bcrypt($data['password']);
            $data['roles'] = [['role_code' => 'new_user', 'status' => true]];
            $data['email_verified_at'] = date('Y-m-d');
            $user = $this->UserRepository->create($data);
            $EmailVerificationRepository->delete($data['code']);

            return ApiResponse::success([
                'type' => 'Bearer',
                'token' => $this->UserRepository->createToken($data['email']),
                'user' => $user,
            ]);
        } else {
            return ApiResponse::error("The email is not verified, please repeat again ", Response::HTTP_UNAUTHORIZED);
        }
    }


    /**
     * @param array $data
     * @return JsonResponse
     * @throws Throwable
     */
    public function resetPassword(array $data): JsonResponse
    {
        $EmailVerificationRepository = $this->EmailVerificationCodeRepository->findByEmail($data['email']);

        if ($EmailVerificationRepository and Hash::check($data['code'], $EmailVerificationRepository->code))
        {
            $user = $this->UserRepository->findByEmail($data['email']);
            $user->password = bcrypt($data['password']);
            $user->save();
            $EmailVerificationRepository->delete();

            return ApiResponse::success([
                'type' => 'Bearer',
                'token' => $this->UserRepository->createToken($data['email']),
                'user' => $user,
            ]);
        } else {
            return ApiResponse::error("The email is not verified , please repeat again ", Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @return int
     * @throws Throwable
     */
    public function logout(): int
    {
        return $this->UserRepository->removeToken(auth()->user());
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Dtos\ApiResponse;
use Illuminate\Http\Request;
use Throwable;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\resetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * @var AuthService
     */
    private AuthService $service;

    /**
     * @param AuthService $service
     */
    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->service->login($request->validated());
    }

    /**
     * @param RegisterRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->service->register($request->validated());
    }

    /**
     * @param resetPasswordRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->service->resetPassword($request->validated());
    }

    /**
     * @return JsonResponse
     * @throws Throwable
     */
    public function logout(): JsonResponse
    {
        return ApiResponse::Success($this->service->logout());
    }


    /**
     * @return JsonResponse
     */
    public function checkUserToken(): JsonResponse
    {
        $success = Auth()->user();
        
        if ($success)
        {
            return ApiResponse::Success($success);
        } else {
            return ApiResponse::Error("Error Email not found!", Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function updateYourself(Request $request)
    {
        auth()->user()->update($request->all());

        return ApiResponse::Success(auth()->user());
    }
}

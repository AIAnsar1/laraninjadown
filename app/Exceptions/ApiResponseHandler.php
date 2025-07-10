<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Traits\ConvertsExceptionToApiResponse;
use Throwable;
class ApiResponseHandler extends Exception
{
    use ConvertsExceptionToApiResponse;

    public function render($request, Throwable $e)
    {
        return $this->renderApiResponse($e, $request);
    }
}

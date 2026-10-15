<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    //未認証時のリダイレクト先を振り分ける。
    protected function unauthenticated(
        $request,
        AuthenticationException $exception
    ) {
        if ($request->is('admin/*')) {
            return redirect()->guest(route('admin.login'));
        }

        return redirect()->guest(route('login'));
    }
}

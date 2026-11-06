<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * APIログインを行い、認証トークンを発行する
     *
     * @param  Request  $request  メールアドレスとパスワードを受け取るリクエスト
     * @return JsonResponse ログイン結果と認証トークンを含むJSONレスポンス
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'メールアドレスは必須です。',
            'email.email' => '正しいメールアドレスを入力してください',
            'password.required' => 'パスワードは必須です。',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 401);
        }

        $token = $user->createToken('attendance-api')->plainTextToken;

        return response()->json([
            'message' => 'ログインしました。',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}

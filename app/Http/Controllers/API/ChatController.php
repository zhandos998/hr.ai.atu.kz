<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = $request->user();

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini', // можешь заменить на gpt-4o-mini, если используешь mini
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Ты AI HR-ассистент отдела кадров АТУ. Отвечай вежливо и ясно, информируя о вакансиях, университете и процессе приема документов. Если кандидат готов подать резюме, сообщи, что нужно зарегистрироваться на сайте и перейти в раздел загрузки резюме."
                ],
                [
                    'role' => 'user',
                    'content' => $request->message
                ],
            ],
        ]);

        return response()->json([
            'message' => $response->choices[0]->message->content
        ]);
    }
}

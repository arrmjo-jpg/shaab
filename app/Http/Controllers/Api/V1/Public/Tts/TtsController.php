<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Tts;

use App\Actions\Public\Tts\GenerateSpeechAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Tts\SpeakRequest;
use Illuminate\Http\JsonResponse;

/**
 * توليد صوت «الاستماع للمقال» (عامّ، throttle:public.tts). محكوم بإعدادات Spatie في الـAction.
 */
class TtsController extends Controller
{
    public function speak(SpeakRequest $request): JsonResponse
    {
        return (new GenerateSpeechAction)->handle($request->validated()['text']);
    }
}

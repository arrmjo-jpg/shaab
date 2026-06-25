<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Meilisearch\Exceptions\CommunicationException;

/**
 * تعطّل النقل/الاتصال بمحرّك البحث (Meilisearch غير متاح) يجب ألّا يُسقِط أي كتابة
 * تحريرية — لكن أخطاء التطبيق الحقيقية يجب أن تتسرّب ولا تُخفى.
 */
function bindThrowingSearchEngine(Throwable $error): void
{
    config(['scout.queue' => false, 'scout.driver' => 'throwing']);
    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('update')->andThrow($error);
    app(EngineManager::class)->extend('throwing', fn () => $engine);
}

it('swallows search transport failures so editorial writes never break', function (): void {
    bindThrowingSearchEngine(new CommunicationException('cURL error 7: connection refused'));
    Log::spy();

    $article = new Article;
    // كان يُرجِع 500؛ الآن يُبتلَع بأمان (عطل اتصال).
    $article->queueMakeSearchable(new EloquentCollection([$article]));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'search.sync_failed');
});

it('re-throws genuine application errors instead of hiding them', function (): void {
    bindThrowingSearchEngine(new TypeError('toSearchableArray() must return array, null given'));

    $article = new Article;

    expect(fn () => $article->queueMakeSearchable(new EloquentCollection([$article])))
        ->toThrow(TypeError::class);
});

it('detects a transport failure wrapped in a previous-exception chain', function (): void {
    $wrapped = new RuntimeException('engine update failed', 0, new CommunicationException('timeout'));
    bindThrowingSearchEngine($wrapped);
    Log::spy();

    $article = new Article;
    $article->queueMakeSearchable(new EloquentCollection([$article]));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'search.sync_failed');
});

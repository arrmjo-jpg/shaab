<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\Article;
use App\Models\Reel;
use App\Models\Video;
use App\Notifications\ContentStatusChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * يُخطر كاتب المحتوى عند تغيّر حالة التحرير لمحتواه. يُستدعى من Transition
 * Actions الثلاثة **بعد commit وخارج أي transaction** (نقطة post-commit).
 *
 * قرارات معتمدة (P1.2):
 *  - نشر/رفض فقط (لا in_review ولا غيره).
 *  - حارس وجود الجدول: قبل تشغيل migrate لا يوجد جدول notifications → تخطٍّ صامت.
 *  - أفضل-جهد: استثناء الإرسال لا يكسر الانتقال التحريري أبداً (try/catch + Log).
 *  - المُستقبِل = author_id (الكاتب)، لا published_by_id (المحرّر).
 */
final class WriterNotifier
{
    /** الحالات التي تُولِّد إشعاراً للكاتب — نشر/رفض فقط. */
    private const NOTIFIABLE = ['published', 'rejected'];

    public static function contentStatusChanged(Article|Reel|Video $content, string $contentType, string $status): void
    {
        if (! in_array($status, self::NOTIFIABLE, true)) {
            return;
        }

        // حارس وجود الجدول — يمنع 500 في نافذة ما-قبل-migrate.
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $author = $content->author;
        if ($author === null) {
            return;
        }

        try {
            $author->notify(new ContentStatusChanged(
                contentType: $contentType,
                contentId: (int) $content->getKey(),
                title: (string) $content->title,
                slug: (string) $content->slug,
                status: $status,
            ));
        } catch (\Throwable $e) {
            Log::warning('writer notification dispatch failed', [
                'content_type' => $contentType,
                'content_id' => $content->getKey(),
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

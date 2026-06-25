<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Models\Broadcast;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس التفاعل مع البثّ (B7) — مرآة BroadcastTransitionGuard (يُرجِع JsonResponse عند
 * الرفض أو null عند السماح). يجمع قاعدتين:
 *
 *   • الرؤية: التفاعل متاح فقط لبثّ له صفحة عامة (publiclyVisible = عام + ليس
 *     مسودّة/مؤرشفاً). قرار منتج صريح: live/scheduled/offline/ended/failed كلها تقبل
 *     التفاعل (كيان محتوى ذو صفحة؛ الحالات العابرة offline/failed أو النهائية ended لا
 *     تُبطِل قابلية التفاعل — كما يتفاعل المستخدم مع محتوى منشور سابق). draft/archived ⇒ 404.
 *
 *   • الإشراف (B6): يُرفَض التفاعل إن كان الجمهور مُغلقاً (closed/إيقاف طارئ) أو كان
 *     العضو محظوراً — عبر طبقة التحكّم نفسها (لا تزييف للتكامل). الطرد (kick) حالة حضور
 *     عابرة لا تمنع التفاعل (يجوز للمطرود العودة).
 */
final class BroadcastReactionGuard
{
    /** الرؤية فقط (للقراءة): هل للبثّ صفحة عامة تقبل التفاعل؟ */
    public static function visible(Broadcast $broadcast): bool
    {
        return BroadcastPresenceControl::isPubliclyVisible($broadcast->status->value, (bool) $broadcast->is_public);
    }

    /** فحص كامل للكتابة (رؤية + إشراف). $member = هوية الفاعل المُصادَق ("u{id}"). */
    public static function check(Broadcast $broadcast, string $member): ?JsonResponse
    {
        if (! self::visible($broadcast)) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        if (BroadcastPresenceControl::isClosed($broadcast->id)) {
            return ApiResponse::error(__('broadcast.reaction.closed'), [], 403);
        }

        if (BroadcastPresenceControl::isBanned($broadcast->id, $member)) {
            return ApiResponse::error(__('broadcast.reaction.banned'), [], 403);
        }

        return null;
    }
}

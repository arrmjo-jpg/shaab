<?php

declare(strict_types=1);

namespace App\Support\Polls;

use App\Support\Advertising\AdClientIp;
use App\Support\Engagement\EngagementActor;
use Illuminate\Http\Request;

/**
 * هويّة الناخب لمنع التكرار (Phase 2). Tier A افتراضاً: تجزئة هويّة الفاعل
 * (مستخدم مُصادَق أو بصمة client-id) عبر EngagementActor. Tier B (strict_ip، مُعطَّل
 * افتراضاً): يطوي بادئة IP (/64) عبر AdClientIp للزوّار — أقوى لكنه يطوي الشبكات المشتركة.
 *
 * تُخزَّن التجزئة فقط (لا IP خام — خصوصية). صحّة فولْد الـ IP تعتمد على ضبط TrustProxies.
 */
final class PollVoter
{
    public static function hash(EngagementActor $actor, Request $request): string
    {
        $seed = $actor->key();

        // Tier B (مُعطَّل افتراضاً) — يُطبَّق على الزوّار فقط؛ المُصادَقون مرتكزون على المستخدم.
        if ($actor->userId === null && (bool) config('polls.dedup.strict_ip', false)) {
            $seed .= '|ip:'.AdClientIp::key($request);
        }

        return hash('sha256', $seed);
    }
}

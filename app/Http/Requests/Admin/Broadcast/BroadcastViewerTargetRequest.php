<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

use App\Http\Requests\BaseFormRequest;

/**
 * استهداف مشاهد للإشراف. طريقتان واقعيتان (الحضور قائم على Redis، لا سجلّ أعضاء):
 *   • user_id  → مُصادَق، يُحلّ إلى العضو "u{id}" (إنفاذ قويّ — هوية ثابتة).
 *   • member   → مُعرّف عضو الحضور المُبهَم ("u…"/"f…")؛ للزوّار أفضل-جهد، يُؤخَذ من
 *     إشارة خارجة عن النطاق (تفاعل مُبلَّغ في B7، أو ما يكشفه العميل). مُقيَّد بالنمط
 *     منعاً لحظر نصوص عشوائية. أحدهما مطلوب.
 */
class BroadcastViewerTargetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required_without:member', 'nullable', 'integer', 'exists:users,id'],
            'member' => ['required_without:user_id', 'nullable', 'string', 'max:80', 'regex:/^[uf][A-Za-z0-9]+$/'],
        ];
    }

    /** يحسم هوية عضو الحضور المستهدَف (يُفضَّل user_id ⇒ إنفاذ قويّ). */
    public function resolvedMember(): string
    {
        $userId = $this->input('user_id');

        return $userId !== null && $userId !== ''
            ? 'u'.(int) $userId
            : (string) $this->input('member');
    }
}

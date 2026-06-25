<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Enums\ArticleType;
use App\Http\Requests\BaseFormRequest;
use App\Models\Article;
use App\Rules\OwnedMediaAsset;
use App\Rules\ValidTipTapDocument;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * إرسال مقال من الكاتب (نطاق عام — V1: أخبار/رأي فقط).
 *
 * نسخة محدودة من StoreArticleRequest الإداري: تقبل حقول المحتوى التي يوفّرها
 * الكاتب فقط. مُستبعَد عمداً (يُفرَض الباقي في الـ Actions/Guards القائمة دون لمسها):
 *  - النوع محصور بـ news|opinion (لا live — قرار مقفول للإدارة فقط).
 *  - author_id: يُربط ذاتياً في ArticleAuthorizationGuard (منع الانتحال).
 *  - status / views_count: الإنشاء = Draft إجباري؛ المشاهدات تحريرية.
 *  - الأعلام التحريرية (is_featured/is_breaking/is_pinned/is_header/
 *    is_editor_pick/comments_enabled): قرار تحريري لا يملكه الكاتب.
 *
 * مسموحٌ مع حارس ملكيّة صارم (Slice 1 — Writer Media Ownership Layer):
 *  - media / og_image_id: يقبلهما الكاتب لكن عبر OwnedMediaAsset فقط — لا يربط
 *    إلا أصولاً رفعها هو (media_assets.uploaded_by)، سدّاً لـ IDOR بين الكتّاب.
 *    الإسناد الفعليّ عبر MediaAttachmentSyncer داخل CreateArticleAction (لا منطق جديد).
 */
class PublicStoreArticleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'locale' => ['required', 'string', Rule::in(Article::LOCALES)],
            // النوع محصور: الكاتب يُرسل أخباراً أو رأياً فقط (لا live).
            'type' => ['required', Rule::in([ArticleType::News->value, ArticleType::Opinion->value])],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            // اختيار متعدّد بلا حدّ أقصى (نموذج الأقسام الموحّد).
            'secondary_category_ids' => ['sometimes', 'array'],
            'secondary_category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],

            // slug يدوي اختياري — سياسة أحرف يونيكود (تشمل العربية)،
            // فرادة لكل لغة (تشمل المحذوف منطقياً عبر فحص الجدول).
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('articles', 'slug')->where(
                    fn ($q) => $q->where('locale', $this->input('locale'))
                ),
            ],

            'subtitle' => ['sometimes', 'nullable', 'string', 'max:250'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // المصدر الأساسي TipTap JSON بقائمة سماح صارمة.
            'content_json' => ['required', 'array', new ValidTipTapDocument],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'min:1', 'max:50'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            // ربط وسائط المكتبة المركزية — نفس شكل StoreArticleRequest الإداري،
            // لكن كل أصل يمرّ بحارس الملكيّة OwnedMediaAsset (الكاتب يملكه فعلاً).
            'og_image_id' => ['sometimes', 'nullable', 'integer', new OwnedMediaAsset($this->user()?->id)],
            'media' => ['sometimes', 'array'],
            'media.*.asset_id' => ['required', 'integer', 'distinct', new OwnedMediaAsset($this->user()?->id)],
            'media.*.collection' => ['required', Rule::in(['cover', 'gallery', 'inline', 'video'])],
            'media.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** غلاف واحد كحدّ أقصى (مطابق لـ StoreArticleRequest الإداري). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            $media = $this->input('media');
            if (! is_array($media)) {
                return;
            }
            $covers = array_filter(
                $media,
                fn ($m): bool => is_array($m) && ($m['collection'] ?? null) === 'cover'
            );
            if (count($covers) > 1) {
                $v->errors()->add('media', __('article.media.single_cover'));
            }
        });
    }
}

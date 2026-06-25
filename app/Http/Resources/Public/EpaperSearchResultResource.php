<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Models\EpaperPage;
use App\Support\Epaper\EpaperPageSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * نتيجة بحث صفحة واحدة داخل العدد: رقم الصفحة + مقتطف حول التطابق + عدد التطابقات.
 * يقرأ الاستعلام (q) من الطلب الحاليّ (تحقّق سابق في EpaperSearchRequest).
 *
 * @mixin EpaperPage
 */
class EpaperSearchResultResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $query = trim((string) $request->query('q', ''));
        $text = (string) $this->text;

        return [
            'page' => $this->page_number,
            'snippet' => EpaperPageSearch::snippet($text, $query),
            'matches' => EpaperPageSearch::matchCount($text, $query),
        ];
    }
}

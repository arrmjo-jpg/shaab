<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * استيراد جهات اتصال من CSV/XLSX — الملف بعمودَي name و phone (ترويسة في الصف الأول).
 * group_id = المجموعة الوجهة (كل صف صالح يُسنَد إليها). duplicates = سياسة التكرار.
 */
class ImportWhatsappContactsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Excel = xlsx (openspout لا يقرأ xls الثنائي القديم) + csv/txt.
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'group_id' => ['required', 'integer',
                Rule::exists('whatsapp_groups', 'id')->whereNull('deleted_at')],
            'duplicates' => ['required', 'string', Rule::in(['update', 'skip'])],
        ];
    }
}

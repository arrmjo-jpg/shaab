<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * استيراد جهات الاتصال من CSV/XLSX — قراءة تدفقية صفّاً بصف (openspout). كل صف صالح
 * يُسنَد للمجموعة الوجهة (group_id). التكرار: update (يحدّث الاسم ويضمّ المجموعة) أو
 * skip (يتجاهل). تقرير نهائي: created/updated/skipped/failed + أسباب الأخطاء (محدودة).
 * متزامن مع سقف صفوف (تقرير فوريّ، بلا Job — الطلب: تقرير بعدد الناجح/الفاشل).
 */
class ImportWhatsappContactsAction
{
    private const MAX_ROWS = 10000;

    private const MAX_ERRORS = 100;

    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $validated['file'];
        $groupId = (int) $validated['group_id'];
        $updateExisting = $validated['duplicates'] === 'update';

        $report = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $reader = strtolower((string) $file->getClientOriginalExtension()) === 'xlsx'
            ? new XlsxReader
            : new CsvReader;

        try {
            $reader->open($file->getRealPath());
        } catch (Throwable $e) {
            return ApiResponse::error(__('whatsapp.import.unreadable'), ['reason' => $e->getMessage()], 422);
        }

        $rowIndex = 0;
        $colName = 0;
        $colPhone = 1;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(static fn ($v): string => trim((string) $v), $row->toArray());
                $rowIndex++;

                // الصف الأول = ترويسة: نحدّد مواقع name/phone (وإلا الافتراضي 0/1).
                if ($rowIndex === 1) {
                    [$colName, $colPhone] = $this->resolveColumns($cells);

                    continue;
                }

                if ($report['total'] >= self::MAX_ROWS) {
                    $report['errors'][] = ['row' => $rowIndex, 'reason' => __('whatsapp.import.row_limit', ['max' => self::MAX_ROWS])];
                    break 2;
                }

                $name = $cells[$colName] ?? '';
                $rawPhone = $cells[$colPhone] ?? '';
                if ($name === '' && $rawPhone === '') {
                    continue; // صف فارغ — تجاهل صامت
                }
                $report['total']++;

                $outcome = $this->importRow($name, $rawPhone, $groupId, $updateExisting);
                $report[$outcome['result']]++;
                if ($outcome['result'] === 'failed' && count($report['errors']) < self::MAX_ERRORS) {
                    $report['errors'][] = ['row' => $rowIndex, 'value' => $rawPhone, 'reason' => $outcome['reason']];
                }
            }
        }

        $reader->close();

        return ApiResponse::success(__('whatsapp.import.done'), $report);
    }

    /**
     * @return array{result: 'created'|'updated'|'skipped'|'failed', reason?: string}
     */
    private function importRow(string $name, string $rawPhone, int $groupId, bool $updateExisting): array
    {
        if ($name === '') {
            return ['result' => 'failed', 'reason' => __('whatsapp.import.name_missing')];
        }

        $phone = PhoneNumber::normalize($rawPhone);
        if ($phone === null) {
            return ['result' => 'failed', 'reason' => __('whatsapp.contact.invalid_phone')];
        }

        try {
            return DB::transaction(function () use ($name, $phone, $groupId, $updateExisting): array {
                $existing = WhatsappContact::withTrashed()->where('phone', $phone)->first();

                if ($existing !== null) {
                    if (! $updateExisting && ! $existing->trashed()) {
                        return ['result' => 'skipped'];
                    }
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->name = $name;
                    $existing->status = WhatsappContactStatus::Subscribed;
                    $existing->source = 'import';
                    $existing->save();
                    $existing->groups()->syncWithoutDetaching([$groupId]);

                    return ['result' => 'updated'];
                }

                $contact = WhatsappContact::create([
                    'name' => $name,
                    'phone' => $phone,
                    'status' => WhatsappContactStatus::Subscribed->value,
                    'source' => 'import',
                ]);
                $contact->groups()->sync([$groupId]);

                return ['result' => 'created'];
            });
        } catch (Throwable $e) {
            return ['result' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * يحدّد موقعَي عمودَي name و phone من صف الترويسة (بحث غير حسّاس لحالة الأحرف).
     *
     * @param  array<int,string>  $header
     * @return array{0:int,1:int}
     */
    private function resolveColumns(array $header): array
    {
        $colName = 0;
        $colPhone = 1;
        foreach ($header as $i => $label) {
            $key = strtolower($label);
            if (in_array($key, ['name', 'الاسم'], true)) {
                $colName = $i;
            } elseif (in_array($key, ['phone', 'رقم', 'الهاتف', 'الرقم'], true)) {
                $colPhone = $i;
            }
        }

        return [$colName, $colPhone];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Models\WhatsappContact;
use Illuminate\Contracts\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * تصدير جهات الاتصال إلى CSV/XLSX (openspout) — يحترم نفس فلاتر القائمة (بحث/مجموعة/حالة).
 * الأعمدة: name, phone, groups, status. يُكتب لملف مؤقت ثم يُبثّ كتنزيل ويُحذف بعد الإرسال.
 */
class ExportWhatsappContactsAction
{
    public function handle(): BinaryFileResponse
    {
        $format = request()->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $tmpPath = tempnam(sys_get_temp_dir(), 'wa_contacts_').'.'.$format;
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($tmpPath);
        $writer->addRow(Row::fromValues(['name', 'phone', 'groups', 'status']));

        $this->query()->chunk(500, function ($contacts) use ($writer): void {
            foreach ($contacts as $contact) {
                $writer->addRow(Row::fromValues([
                    $contact->name,
                    $contact->phone,
                    $contact->groups->pluck('name')->implode(' | '),
                    $contact->status->value,
                ]));
            }
        });

        $writer->close();

        $filename = 'whatsapp-contacts-'.date('Ymd-His').'.'.$format;

        return response()->download($tmpPath, $filename, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    /** نفس فلاتر ListWhatsappContactsAction (بحث/مجموعة/حالة) — تصدير ما يراه المستخدم. */
    private function query(): Builder
    {
        $query = WhatsappContact::query()->with('groups:id,name');

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, WhatsappContactStatus::values(), true)) {
            $query->where('status', $status);
        }

        $groupId = (int) request()->integer('group_id', 0);
        if ($groupId > 0) {
            $query->whereHas('groups', fn (Builder $q) => $q->where('whatsapp_groups.id', $groupId));
        }

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query->orderByDesc('created_at');
    }
}

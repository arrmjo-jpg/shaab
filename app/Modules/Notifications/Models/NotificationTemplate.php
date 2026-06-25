<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;

/**
 * قالب إشعار لكلّ (event × channel × locale). يُصيَّر **مرّة واحدة عند إنشاء الحملة** ويُخزَّن
 * الناتج في snapshot القناة (لا re-render وقت الإرسال). مُدقَّق (إعداد). المتغيّرات المدعومة لكلّ
 * حدث مُوثَّقة في EventCatalog::variablesFor().
 */
class NotificationTemplate extends Model
{
    use AuditsChanges;

    protected $table = 'notification_templates';

    protected string $auditLogName = 'notification_template';

    /** @var array<int,string> */
    protected array $auditAttributes = ['event_key', 'channel', 'locale', 'is_default'];

    protected $fillable = [
        'event_key', 'channel', 'locale', 'title', 'body', 'image_strategy',
        'deep_link_type', 'deep_link_value', 'variables', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelKey::class,
            'variables' => 'array',
            'is_default' => 'boolean',
        ];
    }
}

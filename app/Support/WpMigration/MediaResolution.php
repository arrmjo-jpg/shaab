<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

/**
 * نتيجة حلّ مرجع صورة من متن ووردبريس:
 *  - local      : ملف موجود وآمن داخل جذر uploads (path مطلق مُتحقَّق).
 *  - external   : رابط http(s) خارجي (يجلبه المستورد بأمان SSRF + حدود).
 *  - unresolved : تعذّر الحلّ (reason = MigrationFailureReason) — يُوسَم، لا يُفسد المتن.
 */
final class MediaResolution
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $path,
        public readonly ?string $url,
        public readonly ?string $reason,
    ) {}

    public static function local(string $path): self
    {
        return new self('local', $path, null, null);
    }

    public static function external(string $url): self
    {
        return new self('external', null, $url, null);
    }

    public static function unresolved(string $reason): self
    {
        return new self('unresolved', null, null, $reason);
    }

    public function isLocal(): bool
    {
        return $this->kind === 'local';
    }

    public function isExternal(): bool
    {
        return $this->kind === 'external';
    }

    public function isUnresolved(): bool
    {
        return $this->kind === 'unresolved';
    }
}

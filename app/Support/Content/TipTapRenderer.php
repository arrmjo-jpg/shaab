<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * عرض HTML مُعقَّم مشتقّ من TipTap JSON (للفهرسة/التقديم فقط).
 * مصدر الحقيقة يبقى content_json. كل النصوص/السمات تُهرَّب (e()).
 * التضمينات تُخرَج كعنصر نائب data-* (لا iframe/HTML خام من الخادم).
 */
final class TipTapRenderer
{
    public static function toHtml(?array $doc): string
    {
        if ($doc === null || ($doc['type'] ?? null) !== 'doc') {
            return '';
        }

        return self::children($doc);
    }

    private static function children(array $node): string
    {
        $html = '';
        foreach (($node['content'] ?? []) as $child) {
            $html .= self::node($child);
        }

        return $html;
    }

    private static function node(array $n): string
    {
        return match ($n['type']) {
            'paragraph' => '<p'.self::alignStyle($n).'>'.self::children($n).'</p>',
            'heading' => self::heading($n),
            'blockquote' => '<blockquote>'.self::children($n).'</blockquote>',
            'bulletList' => '<ul>'.self::children($n).'</ul>',
            'orderedList' => '<ol>'.self::children($n).'</ol>',
            'listItem' => '<li>'.self::children($n).'</li>',
            'codeBlock' => '<pre><code>'.self::children($n).'</code></pre>',
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'text' => self::text($n),
            'image' => self::image($n),
            'embed' => self::embed($n),
            'poll' => self::poll($n),
            'table' => '<table>'.self::children($n).'</table>',
            'tableRow' => '<tr>'.self::children($n).'</tr>',
            'tableHeader' => '<th>'.self::children($n).'</th>',
            'tableCell' => '<td>'.self::children($n).'</td>',
            default => '',
        };
    }

    private static function heading(array $n): string
    {
        $lvl = max(1, min(6, (int) ($n['attrs']['level'] ?? 2)));

        return "<h{$lvl}".self::alignStyle($n).'>'.self::children($n)."</h{$lvl}>";
    }

    /** نمط محاذاة النص للفقرة/العنوان (مقيّد بقيم معروفة). */
    private static function alignStyle(array $n): string
    {
        $align = $n['attrs']['textAlign'] ?? null;

        return in_array($align, ['center', 'right', 'justify'], true)
            ? ' style="text-align:'.$align.'"'
            : '';
    }

    private static function text(array $n): string
    {
        $text = e($n['text'] ?? '');

        foreach (($n['marks'] ?? []) as $m) {
            $text = match ($m['type']) {
                'bold' => "<strong>{$text}</strong>",
                'italic' => "<em>{$text}</em>",
                'underline' => "<u>{$text}</u>",
                'strike' => "<s>{$text}</s>",
                'code' => "<code>{$text}</code>",
                'link' => sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
                    e($m['attrs']['href'] ?? '#'),
                    $text,
                ),
                default => $text,
            };
        }

        return $text;
    }

    private static function image(array $n): string
    {
        return sprintf(
            '<img src="%s" alt="%s">',
            e($n['attrs']['src'] ?? ''),
            e($n['attrs']['alt'] ?? ''),
        );
    }

    private static function embed(array $n): string
    {
        return sprintf(
            '<figure data-embed-provider="%s" data-embed-url="%s"></figure>',
            e($n['attrs']['provider'] ?? ''),
            e($n['attrs']['embed_url'] ?? ''),
        );
    }

    /**
     * عقدة استطلاع (Phase 3) — عنصر نائب بالـ uuid فقط (لا بيانات استطلاع مضمّنة)؛
     * يُهيَّأ على العميل عبر data-poll-uuid (مثل التضمينات)، فيبقى كاش المقال مفصولاً
     * عن دورة حياة الاستطلاع.
     */
    private static function poll(array $n): string
    {
        return sprintf(
            '<figure data-poll-uuid="%s"></figure>',
            e($n['attrs']['uuid'] ?? ''),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * محوّل فيديو إلى HLS (adaptive bitrate) عبر FFmpeg — مُكيَّف من sawt
 * (VideoHlsTranscoder). يعمل على مسارات محلّية فقط؛ تنزيل/رفع القرص
 * مسؤولية TranscodeVideoAssetJob (يدعم R2).
 *
 * - اختيار ذكي للدقّات (لا upscaling).
 * - libx264 + AAC، segments .ts، master.m3u8 مكتوب يدوياً لضمان BANDWIDTH.
 * - المسار الثنائي قابل للضبط عبر config('services.ffmpeg.*').
 */
class VideoTranscoder
{
    /** @var array<string,array{height:int,bitrate:int,audio:int,crf:int}> */
    private const PROFILES = [
        '1080p' => ['height' => 1080, 'bitrate' => 5000, 'audio' => 192, 'crf' => 22],
        '720p' => ['height' => 720, 'bitrate' => 2800, 'audio' => 128, 'crf' => 23],
        '480p' => ['height' => 480, 'bitrate' => 1400, 'audio' => 96, 'crf' => 24],
        '360p' => ['height' => 360, 'bitrate' => 800, 'audio' => 64, 'crf' => 25],
    ];

    private const SEGMENT_DURATION = 6;

    private const HLS_TIMEOUT = 1800;

    /**
     * استخراج المدّة والأبعاد عبر ffprobe.
     *
     * @return array{duration:?int,width:?int,height:?int}
     */
    public function probe(string $inputPath): array
    {
        $cmd = [
            $this->binary('ffprobe'),
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:format=duration',
            '-of', 'json',
            $inputPath,
        ];

        $result = Process::timeout(60)->run($cmd);
        if (! $result->successful()) {
            return ['duration' => null, 'width' => null, 'height' => null];
        }

        try {
            $data = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['duration' => null, 'width' => null, 'height' => null];
        }

        $stream = $data['streams'][0] ?? [];
        $format = $data['format'] ?? [];

        return [
            'duration' => isset($format['duration']) ? (int) round((float) $format['duration']) : null,
            'width' => isset($stream['width']) ? (int) $stream['width'] : null,
            'height' => isset($stream['height']) ? (int) $stream['height'] : null,
        ];
    }

    /** يستخرج لقطة غلاف (poster) عند الثانية 3 مع تراجع إلى أوّل فريم. */
    public function poster(string $inputPath, string $outputPath): bool
    {
        $base = ['-y', '-i', $inputPath, '-vframes', '1', '-vf', "scale='min(1280,iw)':'-2'", '-q:v', '2', $outputPath];

        $ok = $this->run($this->ffmpeg(array_merge(['-ss', '00:00:03'], $base)), 60);
        if (! $ok || ! is_file($outputPath) || filesize($outputPath) < 512) {
            @unlink($outputPath);
            $ok = $this->run($this->ffmpeg($base), 60);
        }

        return $ok && is_file($outputPath) && filesize($outputPath) >= 512;
    }

    /** يحوّل صورة (poster JPG) إلى WebP. أفضل جهد — يفشل بهدوء فيبقى الـ JPG. */
    public function thumbnailWebp(string $jpgPath, string $webpPath): bool
    {
        if (! is_file($jpgPath)) {
            return false;
        }

        $ok = $this->run($this->ffmpeg(['-y', '-i', $jpgPath, '-c:v', 'libwebp', '-quality', '82', $webpPath]), 60);

        return $ok && is_file($webpPath) && filesize($webpPath) >= 256;
    }

    /**
     * يولّد نسخ MP4 تدريجية (H.264 faststart) بدقّات متعدّدة داخل $outputDir في
     * مسار ffmpeg واحد: فكّ ترميز المصدر مرّة واحدة (split) ثم إنتاج كل دقّة كملف
     * منفصل — بدل فكّ ترميز المصدر لكل دقّة على حدة (أسرع، نفس المخرجات والجودة).
     * بلا upscaling (min(profile, source))، ونسبة الأبعاد محفوظة. master.mp4 =
     * أعلى دقّة مُنتَجة (نسخ ملف، لا ترميز مزدوج).
     *
     * @return array{success:bool,master:?string,variants:array<string,string>}
     */
    public function renditions(string $inputPath, string $outputDir, int $sourceHeight = 0): array
    {
        $fail = ['success' => false, 'master' => null, 'variants' => []];

        if (! is_dir($outputDir) && ! @mkdir($outputDir, 0o755, true) && ! is_dir($outputDir)) {
            return $fail;
        }

        $heights = $this->selectRenditionHeights($sourceHeight);
        if ($heights === []) {
            return $fail;
        }

        if (! $this->run($this->ffmpeg($this->renditionArgs($inputPath, $outputDir, $heights)), self::HLS_TIMEOUT)) {
            return $fail;
        }

        // تحقّق من الملفات المُنتَجة (best-effort لكل دقّة) وابنِ الخريطة تصاعدياً.
        $variants = [];
        foreach ($heights as $height) {
            $out = $outputDir.DIRECTORY_SEPARATOR.$height.'p.mp4';
            if (is_file($out) && filesize($out) >= 1024) {
                $variants[$height.'p'] = $height.'p.mp4';
            }
        }
        if ($variants === []) {
            return $fail;
        }

        // master = أعلى دقّة مُنتَجة (الترتيب تصاعدي ⇒ الأخيرة هي الأعلى).
        $topFile = $outputDir.DIRECTORY_SEPARATOR.end($variants);
        $masterFile = $outputDir.DIRECTORY_SEPARATOR.'master.mp4';
        if (! @copy($topFile, $masterFile)) {
            return $fail;
        }

        return ['success' => true, 'master' => 'master.mp4', 'variants' => $variants];
    }

    /**
     * يبني وسائط ffmpeg لإنتاج كل دقّات MP4 في مسار واحد: split لمرّة فكّ ترميز
     * واحدة، ثم خرج منفصل لكل دقّة بخياراته (CRF حسب الارتفاع، AAC، faststart).
     *
     * @param  array<int,int>  $heights
     * @return array<int,string>
     */
    private function renditionArgs(string $inputPath, string $outputDir, array $heights): array
    {
        $args = ['-y', '-i', $inputPath];

        // [0:v]split=N[v0][v1]...; [v0]scale=-2:'min(h0,ih)'[v0out]; ...
        $labels = '';
        foreach ($heights as $i => $height) {
            $labels .= "[v{$i}]";
        }
        $filter = [sprintf('[0:v]split=%d%s', count($heights), $labels)];
        foreach ($heights as $i => $height) {
            $filter[] = sprintf("[v%d]scale=-2:'min(%d,ih)'[v%dout]", $i, $height, $i);
        }
        $args[] = '-filter_complex';
        $args[] = implode(';', $filter);

        // خرج منفصل لكل دقّة — الخيارات تسبق ملف الخرج فتنطبق عليه وحده.
        foreach ($heights as $i => $height) {
            $crf = match (true) {
                $height >= 1080 => 22,
                $height >= 720 => 23,
                $height >= 480 => 24,
                default => 25,
            };
            $args = array_merge($args, [
                '-map', "[v{$i}out]", '-map', 'a:0?',
                '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'main', '-crf', (string) $crf,
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-ar', '48000',
                '-movflags', '+faststart',
                $outputDir.DIRECTORY_SEPARATOR.$height.'p.mp4',
            ]);
        }

        return $args;
    }

    /**
     * يولّد HLS متعدّد الدقّات داخل $outputDir (master.m3u8 + stream_x/).
     *
     * @return array{success:bool,master:?string,variants:array<string,string>}
     */
    public function hls(string $inputPath, string $outputDir, int $sourceHeight = 0): array
    {
        $fail = ['success' => false, 'master' => null, 'variants' => []];

        if (! is_dir($outputDir) && ! @mkdir($outputDir, 0o755, true) && ! is_dir($outputDir)) {
            return $fail;
        }

        $profiles = $this->selectProfiles($sourceHeight);
        if ($profiles === []) {
            return $fail;
        }

        if (! $this->runFfmpegHls($inputPath, $outputDir, $profiles)) {
            return $fail;
        }

        $variants = $this->writeMasterPlaylist($outputDir.DIRECTORY_SEPARATOR.'master.m3u8', $outputDir, $profiles);
        if ($variants === []) {
            return $fail;
        }

        return ['success' => true, 'master' => 'master.m3u8', 'variants' => $variants];
    }

    /** @return array<int,string> */
    private function selectProfiles(int $sourceHeight): array
    {
        if ($sourceHeight <= 0) {
            return array_keys(self::PROFILES);
        }
        $selected = [];
        foreach (self::PROFILES as $name => $p) {
            if ($p['height'] <= (int) ($sourceHeight * 1.1)) {
                $selected[] = $name;
            }
        }

        return $selected === [] ? [array_key_last(self::PROFILES)] : $selected;
    }

    /**
     * ارتفاعات نسخ MP4 (تصاعدياً) بلا upscaling — تُستبعَد ما يفوق المصدر.
     *
     * @return array<int,int>
     */
    private function selectRenditionHeights(int $sourceHeight): array
    {
        $ladder = [360, 480, 720, 1080];
        if ($sourceHeight <= 0) {
            return $ladder;
        }

        $selected = array_values(array_filter($ladder, fn (int $h): bool => $h <= (int) ($sourceHeight * 1.1)));

        return $selected === [] ? [$ladder[0]] : $selected;
    }

    /** @param array<int,string> $profiles */
    private function runFfmpegHls(string $inputPath, string $outputDir, array $profiles): bool
    {
        $args = ['-y', '-i', $inputPath];

        $splitLabels = '';
        foreach ($profiles as $i => $name) {
            $splitLabels .= "[v{$i}]";
        }
        $filter = [sprintf('[0:v]split=%d%s', count($profiles), $splitLabels)];
        foreach ($profiles as $i => $name) {
            $filter[] = sprintf('[v%d]scale=-2:%d[v%dout]', $i, self::PROFILES[$name]['height'], $i);
        }
        $args[] = '-filter_complex';
        $args[] = implode(';', $filter);

        foreach ($profiles as $i => $name) {
            $p = self::PROFILES[$name];
            $args = array_merge($args, [
                '-map', "[v{$i}out]", '-map', 'a:0?',
                '-c:v:'.$i, 'libx264', '-preset', 'veryfast', '-profile:v:'.$i, 'main',
                '-crf:'.$i, (string) $p['crf'], '-b:v:'.$i, $p['bitrate'].'k',
                '-maxrate:'.$i, (int) ($p['bitrate'] * 1.1).'k', '-bufsize:'.$i, (int) ($p['bitrate'] * 2).'k',
                '-c:a:'.$i, 'aac', '-b:a:'.$i, $p['audio'].'k', '-ac', '2', '-ar', '48000',
            ]);
            $dir = $outputDir.DIRECTORY_SEPARATOR.'stream_'.$i;
            if (! is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }
        }

        // بلا name: في خريطة التدفّقات — كي يُستبدَل %v بفهرس التدفّق (0..n)
        // مطابقاً لمجلّدات stream_0..n المُنشأة أعلاه ولبحث writeMasterPlaylist
        // (لو وُضِع name: لاستبدل ffmpeg %v بالاسم → stream_1080p/ فيختلّ الاقتران).
        $map = [];
        foreach ($profiles as $i => $name) {
            $map[] = "v:{$i},a:{$i}";
        }

        $args = array_merge($args, [
            '-f', 'hls',
            '-hls_time', (string) self::SEGMENT_DURATION,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', $outputDir.DIRECTORY_SEPARATOR.'stream_%v/segment_%05d.ts',
            '-master_pl_name', '_master_ignored.m3u8',
            '-var_stream_map', implode(' ', $map),
            $outputDir.DIRECTORY_SEPARATOR.'stream_%v/playlist.m3u8',
        ]);

        return $this->run($this->ffmpeg($args), self::HLS_TIMEOUT);
    }

    /**
     * @param  array<int,string>  $profiles
     * @return array<string,string>
     */
    private function writeMasterPlaylist(string $masterPath, string $outputDir, array $profiles): array
    {
        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];
        $variants = [];
        foreach ($profiles as $i => $name) {
            $playlist = 'stream_'.$i.'/playlist.m3u8';
            if (! is_file($outputDir.DIRECTORY_SEPARATOR.$playlist)) {
                continue;
            }
            $p = self::PROFILES[$name];
            $bandwidth = ($p['bitrate'] + $p['audio']) * 1000;
            $width = (int) round($p['height'] * 16 / 9);
            $width += $width % 2;
            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$width}x{$p['height']},NAME=\"{$name}\"";
            $lines[] = $playlist;
            $variants[$name] = $playlist;
        }

        if ($variants === []) {
            return [];
        }
        file_put_contents($masterPath, implode("\n", $lines)."\n");

        return $variants;
    }

    /**
     * يبني أمر ffmpeg كمصفوفة وسائط (لا سلسلة نصّية). تمرير المصفوفة إلى
     * Symfony Process يُنفِّذ الثنائي مباشرةً عبر proc_open بلا صدفة — فيُحفَظ
     * المحرف % حرفياً (قوالب HLS مثل %v و%05d) ويُتفادى تشويه cmd.exe على ويندوز،
     * كما يَنتفي الاقتباس اليدوي عبر المنصّات.
     *
     * @param  array<int,string>  $args
     * @return array<int,string>
     */
    private function ffmpeg(array $args): array
    {
        $cmd = array_merge([$this->binary('ffmpeg')], $args);

        // أولوية منخفضة على أنظمة يونكس (لا يوجد nice على ويندوز).
        return PHP_OS_FAMILY !== 'Windows'
            ? array_merge(['nice', '-n', '19'], $cmd)
            : $cmd;
    }

    /** @param array<int,string> $command */
    private function run(array $command, int $timeout): bool
    {
        $result = Process::timeout($timeout)->run($command);
        if (! $result->successful()) {
            Log::warning('VideoTranscoder: ffmpeg failed', [
                'exit' => $result->exitCode(),
                'stderr' => mb_substr($result->errorOutput(), -800),
            ]);
        }

        return $result->successful();
    }

    /**
     * المسار الثنائي الخام (بلا اقتباس) — يُمرَّر كعنصر مصفوفة لـ Process،
     * فلا حاجة لـ escapeshellarg. فارغ ⇒ يُعتمَد على اسم الثنائي من PATH.
     */
    private function binary(string $name): string
    {
        $custom = config("services.ffmpeg.{$name}_path");

        return is_string($custom) && $custom !== '' ? $custom : $name;
    }
}

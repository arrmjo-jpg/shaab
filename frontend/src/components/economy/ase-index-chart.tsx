'use client';

import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import type { AseIndexData } from '@/lib/ase-market';

const UP = '#16a34a';
const DOWN = '#dc2626';

// وقت بصيغة 24س غربيّة (10:30) بتوقيت عمّان — مطابق لمحور المصدر.
function fmtTime(t: number): string {
  try {
    return new Intl.DateTimeFormat('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'Asia/Amman',
    }).format(new Date(t));
  } catch {
    return '';
  }
}

// مخطّط المؤشّر العام الحيّ (recharts AreaChart) — لونه أخضر/أحمر حسب اتّجاه الجلسة.
// البيانات مجلوبة خادميّاً وتُمرَّر prop؛ المكوّن عرض فقط (الحاوية ltr، الصفحة rtl).
export function AseIndexChart({ index }: { index: AseIndexData }) {
  const color = index.dir === 'down' ? DOWN : UP;
  const data = index.series.map((p) => ({ label: fmtTime(p.t), v: p.v }));
  const vals = index.series.map((p) => p.v);
  const min = Math.min(...vals);
  const max = Math.max(...vals);
  const pad = (max - min) * 0.1 || 1;

  return (
    <div className="h-64 w-full" dir="ltr">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 10, right: 4, left: 4, bottom: 0 }}>
          <defs>
            <linearGradient id="aseIdxFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.25} />
              <stop offset="100%" stopColor={color} stopOpacity={0} />
            </linearGradient>
          </defs>
          <XAxis dataKey="label" tick={{ fontSize: 11 }} minTickGap={48} interval="preserveStartEnd" />
          <YAxis
            orientation="right"
            domain={[min - pad, max + pad]}
            tick={{ fontSize: 11 }}
            width={52}
            tickFormatter={(v) => Number(v).toFixed(0)}
          />
          <Tooltip
            formatter={(v) => [Number(v).toFixed(2), 'المؤشّر']}
            contentStyle={{ fontSize: 12, direction: 'rtl' }}
          />
          <Area
            type="monotone"
            dataKey="v"
            stroke={color}
            strokeWidth={2}
            fill="url(#aseIdxFill)"
            dot={false}
            isAnimationActive={false}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

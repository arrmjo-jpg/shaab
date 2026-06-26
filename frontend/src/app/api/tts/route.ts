import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: POST /api/tts → POST /api/v1/tts/speak. يمرّر نصّ المقال + X-Client-Id (لطبقة throttle).
// الميزة محكومة خادميًّا بإعدادات Spatie؛ المفتاح لا يصل المتصفّح. يعيد { success, audio } (data URI).
export async function POST(request: Request): Promise<Response> {
  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, audio: null }, { status: 503 });
  }

  let body: { text?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, audio: null }, { status: 400 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/tts/speak`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: JSON.stringify({ text: body.text ?? '' }),
    });

    const data: { data?: { audio?: string } } = await res.json().catch(() => ({}));
    const audio = data?.data?.audio ?? null;

    return NextResponse.json(
      { success: res.ok && Boolean(audio), audio },
      { status: res.ok ? 200 : res.status },
    );
  } catch {
    return NextResponse.json({ success: false, audio: null }, { status: 502 });
  }
}

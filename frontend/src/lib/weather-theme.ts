// تدرّجات الطقس حسب الحالة والوقت (نهار/ليل) — آمن للعميل (دالّة نقيّة، بلا server-only). يمنح البطاقة
// «مزاجاً» يتغيّر مع الطقس (مظهر عصريّ). يُستخدم في البطاقة (عميل) وشبكة المحافظات (خادم).
export function weatherGradient(icon: string): string {
  const night = icon.endsWith('n');
  switch (icon.slice(0, 2)) {
    case '01': // صافٍ
      return night
        ? 'linear-gradient(160deg,#0a1330 0%,#1b2a6b 55%,#34508f 100%)'
        : 'linear-gradient(160deg,#1668d6 0%,#28a0e6 45%,#7fd0f0 100%)';
    case '02': // غائم جزئيّاً
      return night
        ? 'linear-gradient(160deg,#141e30 0%,#243b55 100%)'
        : 'linear-gradient(160deg,#2479c9 0%,#5aa6d8 55%,#9bcdea 100%)';
    case '03':
    case '04': // غيوم
      return night
        ? 'linear-gradient(160deg,#2b3340 0%,#49586a 100%)'
        : 'linear-gradient(160deg,#5b7383 0%,#8aa0ad 100%)';
    case '09':
    case '10': // مطر
      return night
        ? 'linear-gradient(160deg,#1a2230 0%,#37495d 100%)'
        : 'linear-gradient(160deg,#395f73 0%,#6088a0 100%)';
    case '11': // عواصف رعديّة
      return 'linear-gradient(160deg,#1f1c2c 0%,#3a2e5a 60%,#4b3b73 100%)';
    case '13': // ثلج
      return 'linear-gradient(160deg,#5a86ad 0%,#9fc7e8 60%,#d3ecf8 100%)';
    case '50': // ضباب
      return 'linear-gradient(160deg,#5b6470 0%,#9aa3ad 100%)';
    default:
      return 'linear-gradient(160deg,#1668d6 0%,#28a0e6 45%,#7fd0f0 100%)';
  }
}

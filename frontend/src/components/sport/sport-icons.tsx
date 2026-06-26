import type { SVGProps } from 'react';

// أيقونات رياضات مخصّصة (inline SVG) — lucide في هذا الإصدار لا يوفّر كرة قدم/سلة/تنس/كريكِت.
// نمط lucide: 24×24 · fill=none · stroke=currentColor · 2px · أطراف دائريّة (تتبع لون النصّ ⇒ تتلوّن مع الحالة النشطة).
type IconProps = SVGProps<SVGSVGElement>;

const base: SVGProps<SVGSVGElement> = {
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
};

export function FootballIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="m12 8 3 2.2-1.1 3.6h-3.8L9 10.2z" />
      <path d="M12 8V4M15 10.2 18.6 9M13.9 13.8l2.2 3.1M10.1 13.8l-2.2 3.1M9 10.2 5.4 9" />
    </svg>
  );
}

export function BasketballIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 3v18M3 12h18M5.6 5.6C9 8.2 9 15.8 5.6 18.4M18.4 5.6C15 8.2 15 15.8 18.4 18.4" />
    </svg>
  );
}

export function TennisIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <ellipse cx="10" cy="9" rx="5.5" ry="6" />
      <path d="M6.2 6.5h7.6M10 3.2v11.6" />
      <path d="m13.9 13.4 4.9 5.9" />
    </svg>
  );
}

export function CricketIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <path d="M6 18.5 14.5 10" strokeWidth={3.5} />
      <path d="m13.5 9 3.5-3.5" />
      <circle cx="7" cy="17.5" r="2" />
    </svg>
  );
}

export function HandballIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 12V3M12 12l7.4 4.3M12 12l-7.4 4.3" />
    </svg>
  );
}

export function VolleyballIcon(p: IconProps) {
  return (
    <svg {...base} {...p} aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 3a15 15 0 0 0 0 18M5 7c4.5 2.5 10 3 15 .5M5.5 17.5c2-5 7-8.5 13.5-9" />
    </svg>
  );
}

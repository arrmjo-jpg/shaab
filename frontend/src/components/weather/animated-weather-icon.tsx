import type { CSSProperties } from 'react';

// أيقونات طقس **ملوّنة متحرّكة** (SVG + CSS خالص، بلا مكتبة/صور خارجيّة، بلا hooks ⇒ تصلح خادميّاً وعميليّاً).
// الحركة عبر أصناف globals.css (wx-spin/pulse/drift/rain/flash/snow/fog) — تحترم prefers-reduced-motion.
type Kind = 'sun' | 'moon' | 'cloud-sun' | 'cloud-moon' | 'cloud' | 'drizzle' | 'rain' | 'thunder' | 'snow' | 'mist';

function toKind(code: string): Kind {
  const night = code.endsWith('n');
  switch (code.slice(0, 2)) {
    case '01':
      return night ? 'moon' : 'sun';
    case '02':
      return night ? 'cloud-moon' : 'cloud-sun';
    case '03':
    case '04':
      return 'cloud';
    case '09':
      return 'drizzle';
    case '10':
      return 'rain';
    case '11':
      return 'thunder';
    case '13':
      return 'snow';
    case '50':
      return 'mist';
    default:
      return 'cloud';
  }
}

const RAY_ANGLES = [0, 45, 90, 135, 180, 225, 270, 315];

function Sun({ cx = 32, cy = 30, r = 10, rays = true }: { cx?: number; cy?: number; r?: number; rays?: boolean }) {
  return (
    <>
      {rays && (
        <g className="wx-spin">
          {RAY_ANGLES.map((a) => (
            <rect
              key={a}
              x={cx - 1.5}
              y={cy - r - 9}
              width="3"
              height="6"
              rx="1.5"
              fill="#FFC107"
              transform={`rotate(${a} ${cx} ${cy})`}
            />
          ))}
        </g>
      )}
      <circle cx={cx} cy={cy} r={r} fill="#FFB300" className="wx-pulse" />
      <circle cx={cx - r * 0.28} cy={cy - r * 0.28} r={r * 0.58} fill="#FFD54F" />
    </>
  );
}

function Moon({ cx = 33, cy = 28 }: { cx?: number; cy?: number }) {
  return (
    <path
      className="wx-pulse"
      d={`M${cx + 13} ${cy + 4} a14 14 0 1 1 -15 -18 a11 11 0 0 0 15 18 z`}
      fill="#FFE082"
    />
  );
}

function Cloud({ dark = false, drift = true }: { dark?: boolean; drift?: boolean }) {
  const main = dark ? '#AEB9C4' : '#ffffff';
  const shade = dark ? '#90A0AD' : '#E7EEF5';
  return (
    <g className={drift ? 'wx-drift' : undefined}>
      <circle cx="24" cy="38" r="9" fill={shade} />
      <circle cx="44" cy="39" r="9" fill={shade} />
      <circle cx="35" cy="32" r="12" fill={main} />
      <circle cx="26" cy="37" r="9" fill={main} />
      <rect x="22" y="39" width="24" height="10" fill={main} />
    </g>
  );
}

function Drops({ color, count }: { color: string; count: number }) {
  const xs = count === 2 ? [28, 40] : [25, 34, 43];
  return (
    <>
      {xs.map((x, i) => (
        <line
          key={x}
          x1={x}
          y1="48"
          x2={x - 2}
          y2="55"
          stroke={color}
          strokeWidth="3"
          strokeLinecap="round"
          className="wx-rain"
          style={{ animationDelay: `${i * 0.26}s` } as CSSProperties}
        />
      ))}
    </>
  );
}

export function AnimatedWeatherIcon({ code, className, title }: { code: string; className?: string; title?: string }) {
  const kind = toKind(code);
  return (
    <svg viewBox="0 0 64 64" className={className} role="img" aria-label={title ?? ''} xmlns="http://www.w3.org/2000/svg">
      {kind === 'sun' && <Sun />}
      {kind === 'moon' && <Moon />}
      {kind === 'cloud-sun' && (
        <>
          <Sun cx={43} cy={22} r={8} />
          <Cloud />
        </>
      )}
      {kind === 'cloud-moon' && (
        <>
          <Moon cx={40} cy={20} />
          <Cloud />
        </>
      )}
      {kind === 'cloud' && <Cloud />}
      {kind === 'drizzle' && (
        <>
          <Cloud />
          <Drops color="#4FC3F7" count={2} />
        </>
      )}
      {kind === 'rain' && (
        <>
          <Cloud />
          <Drops color="#4FC3F7" count={3} />
        </>
      )}
      {kind === 'thunder' && (
        <>
          <Cloud dark />
          <path d="M33 46 l-7 11 h5 l-3 8 11 -14 h-5 z" fill="#FFD54F" className="wx-flash" />
        </>
      )}
      {kind === 'snow' && (
        <>
          <Cloud />
          {[25, 34, 43].map((x, i) => (
            <circle
              key={x}
              cx={x}
              cy="51"
              r="2.4"
              fill="#E1F5FE"
              className="wx-snow"
              style={{ animationDelay: `${i * 0.3}s` } as CSSProperties}
            />
          ))}
        </>
      )}
      {kind === 'mist' && (
        <g>
          {[24, 32, 40].map((y, i) => (
            <rect
              key={y}
              x="14"
              y={y}
              width="36"
              height="4"
              rx="2"
              fill="#CFD8DC"
              className="wx-fog"
              style={{ animationDelay: `${i * 0.4}s` } as CSSProperties}
            />
          ))}
        </g>
      )}
    </svg>
  );
}

/** رسوم SVG مجرّدة راقية — تستخدم ألوان العلامة عبر currentColor */

export function LoginArt({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 400 320" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="48" y="56" width="304" height="208" rx="22" className="fill-white/10" />
      <rect x="48" y="56" width="304" height="44" rx="22" className="fill-white/15" />
      <circle cx="74" cy="78" r="6" className="fill-white/40" />
      <circle cx="94" cy="78" r="6" className="fill-white/25" />
      <rect x="72" y="124" width="120" height="14" rx="7" className="fill-white/30" />
      <rect x="72" y="150" width="200" height="10" rx="5" className="fill-white/15" />
      <rect x="72" y="170" width="170" height="10" rx="5" className="fill-white/15" />
      <rect x="72" y="206" width="92" height="30" rx="12" className="fill-white/35" />
      <circle cx="312" cy="196" r="40" className="fill-white/12" />
      <path d="M300 196l9 9 18-19" stroke="currentColor" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round" className="text-white/70" />
    </svg>
  );
}

export function RecoveryArt({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 400 320" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M200 48l108 40v74c0 70-46 108-108 126-62-18-108-56-108-126V88l108-40z" className="fill-white/12" />
      <path d="M200 78l78 29v56c0 50-33 78-78 92-45-14-78-42-78-92v-56l78-29z" className="fill-white/10" />
      <rect x="172" y="160" width="56" height="46" rx="10" className="fill-white/40" />
      <path d="M182 160v-12a18 18 0 0136 0v12" stroke="currentColor" strokeWidth="6" strokeLinecap="round" className="text-white/60" />
      <circle cx="200" cy="180" r="6" className="fill-primary" />
    </svg>
  );
}

export function ResetArt({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 400 320" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="200" cy="160" r="96" className="fill-white/10" />
      <path
        d="M248 160a48 48 0 11-14-34"
        stroke="currentColor"
        strokeWidth="10"
        strokeLinecap="round"
        className="text-white/55"
      />
      <path d="M236 110l4 22-22-4z" className="fill-white/70" />
      <rect x="182" y="150" width="36" height="30" rx="7" className="fill-white/45" />
      <path d="M188 150v-8a12 12 0 0124 0v8" stroke="currentColor" strokeWidth="5" strokeLinecap="round" className="text-white/60" />
    </svg>
  );
}

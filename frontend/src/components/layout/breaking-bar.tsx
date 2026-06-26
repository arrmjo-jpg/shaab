import { Container } from './container';
import { BREAKING } from './nav-data';

// Breaking-news strip — sits directly under the header. Static placeholder content (no API).
export function BreakingBar() {
  return (
    <div className="border-b border-border bg-surface">
      <Container className="flex h-11 items-center gap-3">
        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-primary px-2.5 py-1 text-caption font-extrabold text-primary-foreground">
          <span className="size-1.5 rounded-full bg-primary-foreground motion-safe:animate-pulse" aria-hidden />
          عاجل
        </span>
        <div className="relative flex-1 overflow-hidden">
          <div className="flex items-center gap-7 whitespace-nowrap text-sm font-medium text-fg">
            {BREAKING.map((t, i) => (
              <span key={i} className="inline-flex shrink-0 items-center gap-2">
                <span className="size-1 rounded-full bg-primary" aria-hidden />
                {t}
              </span>
            ))}
          </div>
          <div className="pointer-events-none absolute inset-y-0 start-0 w-12 bg-gradient-to-r from-transparent to-surface" aria-hidden />
        </div>
      </Container>
    </div>
  );
}

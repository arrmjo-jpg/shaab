/**
 * Generate a strong random password that satisfies Laravel's `Password::defaults()`
 * (min 8 chars, mixed case, digits, symbols). 16 chars by default — well above the
 * minimum so even custom Password rules tend to pass.
 *
 * Uses crypto.getRandomValues for cryptographic-quality randomness.
 */
const LOWER = 'abcdefghijkmnopqrstuvwxyz';
const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const DIGITS = '23456789';
const SYMBOLS = '!@#$%^&*()-_+=';

function pickRandom(pool: string, count: number): string {
  const out: string[] = [];
  const arr = new Uint32Array(count);
  crypto.getRandomValues(arr);
  for (let i = 0; i < count; i++) {
    out.push(pool[arr[i] % pool.length]);
  }
  return out.join('');
}

function shuffle(input: string): string {
  const chars = input.split('');
  const arr = new Uint32Array(chars.length);
  crypto.getRandomValues(arr);
  // Fisher-Yates with crypto randomness
  for (let i = chars.length - 1; i > 0; i--) {
    const j = arr[i] % (i + 1);
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }
  return chars.join('');
}

export function generateStrongPassword(length = 16): string {
  // Guarantee at least one of each character class
  const required =
    pickRandom(LOWER, 1) +
    pickRandom(UPPER, 1) +
    pickRandom(DIGITS, 1) +
    pickRandom(SYMBOLS, 1);
  const rest = pickRandom(LOWER + UPPER + DIGITS + SYMBOLS, Math.max(0, length - 4));
  return shuffle(required + rest);
}

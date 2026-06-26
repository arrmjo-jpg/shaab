export const STORAGE_KEYS = {
  token: 'alphacms.admin.token',
  theme: 'alphacms.admin.theme',
  dir: 'alphacms.admin.dir',
  lang: 'alphacms.admin.lang',
} as const;

export const BRAND = {
  name: 'AlphaCMS',
  color: '#3B7597',
} as const;

/** الصورة الافتراضية حين لا يملك المستخدم صورة. تُخدَم من admin-frontend/public/ */
export const DEFAULT_AVATAR = '/img/default-avatar.svg';

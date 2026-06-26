export const queryKeys = {
  authMe: ['auth', 'me'] as const,
  settings: (group: string) => ['settings', group] as const,
  settingsOverview: ['settings', 'overview'] as const,
  cdnStatus: ['cdn', 'status'] as const,
} as const;

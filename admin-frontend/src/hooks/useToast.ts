import Swal from 'sweetalert2';
import { useCallback } from 'react';
import { useTheme } from '@/app/theme';

/** غلاف مركزي لـ SweetAlert2 — يحترم RTL والوضع الليلي */
export function useToast() {
  const { resolved, dir } = useTheme();

  const base = useCallback(
    () =>
      Swal.mixin({
        toast: true,
        position: dir === 'rtl' ? 'top-start' : 'top-end',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        background: resolved === 'dark' ? '#162130' : '#ffffff',
        color: resolved === 'dark' ? '#e5edf5' : '#1f2a37',
      }),
    [dir, resolved],
  );

  const success = useCallback(
    (title: string) => void base().fire({ icon: 'success', title }),
    [base],
  );

  const error = useCallback(
    (title: string) => void base().fire({ icon: 'error', title }),
    [base],
  );

  const confirm = useCallback(
    async (opts: { title: string; text?: string; confirmText: string; cancelText: string }) => {
      const res = await Swal.fire({
        icon: 'warning',
        title: opts.title,
        text: opts.text,
        showCancelButton: true,
        confirmButtonText: opts.confirmText,
        cancelButtonText: opts.cancelText,
        buttonsStyling: false,
        customClass: {
          confirmButton:
            'inline-flex items-center justify-center rounded-xl bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground mx-1',
          cancelButton:
            'inline-flex items-center justify-center rounded-xl bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground mx-1',
        },
        background: resolved === 'dark' ? '#162130' : '#ffffff',
        color: resolved === 'dark' ? '#e5edf5' : '#1f2a37',
      });
      return res.isConfirmed;
    },
    [resolved],
  );

  return { success, error, confirm };
}

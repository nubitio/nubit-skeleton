import { useCallback, useMemo, useState } from 'react';

type NotificationType = 'success' | 'error' | 'warning' | 'info';

type AppRuntime = {
  notify: (message: string, type?: NotificationType, durationMs?: number) => void;
  confirm: (message: string) => boolean;
};

type ToastItem = {
  id: number;
  message: string;
  type: NotificationType;
};

const TYPE_CLASS: Record<NotificationType, string> = {
  success: 'nb-toast--success',
  error: 'nb-toast--error',
  warning: 'nb-toast--warning',
  info: 'nb-toast--info',
};

export function useAppRuntime() {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const dismiss = useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback(
    (message: string, type: NotificationType = 'info', durationMs = 4000) => {
      const id = Date.now() + Math.floor(Math.random() * 1000);
      setToasts((current) => [...current, { id, message, type }]);
      window.setTimeout(() => dismiss(id), durationMs);
    },
    [dismiss],
  );

  const confirm = useCallback((message: string) => {
    if (typeof window === 'undefined') {
      return false;
    }

    return window.confirm(message);
  }, []);

  const runtime = useMemo<AppRuntime>(() => ({ notify, confirm }), [confirm, notify]);

  return { runtime, toasts, dismiss };
}

export function ToastHost({
  toasts,
  onDismiss,
}: {
  toasts: ToastItem[];
  onDismiss: (id: number) => void;
}) {
  if (toasts.length === 0) {
    return null;
  }

  return (
    <div className="nb-toast-host" aria-live="polite">
      {toasts.map((toast) => (
        <div key={toast.id} className={`nb-toast ${TYPE_CLASS[toast.type]}`} role="status">
          <span>{toast.message}</span>
          <button type="button" className="nb-toast__close" onClick={() => onDismiss(toast.id)}>
            ×
          </button>
        </div>
      ))}
    </div>
  );
}
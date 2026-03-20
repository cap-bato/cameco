// hooks/use-payroll-progress.ts
import { useState, useEffect, useRef, useCallback } from 'react';

interface ProgressState {
  status: string;
  progress: number;
  processedEmployees: number;
  totalEmployees: number;
  failedEmployees: number;
  errorMessage: string | null;
  isPolling: boolean;
}

interface UsePayrollProgressOptions {
  calculationId: number;
  initialStatus: string;
  enabled: boolean;
  pollingInterval?: number;
  onComplete?: () => void;
  onFailed?: (error: string | null) => void;
}

export function usePayrollProgress({
  calculationId,
  initialStatus,
  enabled,
  pollingInterval = 2000,
  onComplete,
  onFailed,
}: UsePayrollProgressOptions): ProgressState {
  const [state, setState] = useState<ProgressState>({
    status: initialStatus,
    progress: 0,
    processedEmployees: 0,
    totalEmployees: 0,
    failedEmployees: 0,
    errorMessage: null,
    isPolling: false,
  });

  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const isMounted = useRef(true);

  const stopPolling = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setState(prev => ({ ...prev, isPolling: false }));
  }, []);

  const fetchStatus = useCallback(async () => {
    if (!calculationId) return;

    try {
      const res = await fetch(`/payroll/calculations/${calculationId}/status`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });

    if (!res.ok) {
        if (res.status === 404 || res.status === 403) {
            stopPolling();
        }
        return;
    }
      const data = await res.json();

      if (!isMounted.current) return;

      // Prefer live batch data when available
      const batch = data.batch;
    const total     = batch?.total    ?? data.total_employees     ?? 0;
    const pending   = batch?.pending  ?? null;
    const failed    = batch?.failed   ?? data.failed_employees    ?? 0;
    const progress  = batch?.progress ?? data.progress_percentage ?? 0;
    const processed = pending !== null
        ? Math.max(0, total - pending)
        : data.processed_employees ?? 0;

    setState(prev => ({
        ...prev,
        status:             data.status,
        progress:           isNaN(progress) ? 0 : Math.round(progress),
        processedEmployees: isNaN(processed) ? 0 : processed,
        totalEmployees:     isNaN(total) ? 0 : total,
        failedEmployees:    isNaN(failed) ? 0 : failed,
        errorMessage:       data.error_message ?? null,
    }));

      // Terminal states — stop polling and fire callbacks
      if (data.status === 'completed') {
        stopPolling();
        onComplete?.();
      } else if (data.status === 'failed' || data.status === 'cancelled') {
        stopPolling();
        onFailed?.(data.error_message ?? null);
      }
    } catch (e) {
      // Network error — keep polling, don't crash
      console.warn('Polling error:', e);
    }
  }, [calculationId, stopPolling, onComplete, onFailed]);

  useEffect(() => {
    isMounted.current = true;
    return () => { isMounted.current = false; };
  }, []);

  useEffect(() => {
    if (!enabled || !calculationId) return;

    setState(prev => ({ ...prev, isPolling: true }));

    // Fetch immediately, then on interval
    fetchStatus();
    intervalRef.current = setInterval(fetchStatus, pollingInterval);

    return () => stopPolling();
  }, [enabled, calculationId, pollingInterval, fetchStatus, stopPolling]);

  return state;
}
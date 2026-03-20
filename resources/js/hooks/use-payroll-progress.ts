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
// Inside usePayrollProgress fetchStatus function:

const data = await res.json();
if (!isMounted.current) return;

const batch = data.batch;

// 1. Ensure we fall back to 0 and cast to Number safely
const total = Number(batch?.total ?? data.total_employees ?? 0) || 0;
const pending = Number(batch?.pending ?? 0) || 0;
const failed = Number(batch?.failed ?? data.failed_employees ?? 0) || 0;
const progress = Number(batch?.progress ?? data.progress_percentage ?? 0) || 0;

// 2. Calculate processed safely
const processed = Math.max(0, total - pending);

setState(prev => ({
  ...prev,
  status: data.status || 'processing',
  progress: Math.round(progress),
  processedEmployees: processed,
  totalEmployees: total,
  failedEmployees: failed,
  errorMessage: data.error_message ?? null,
  isPolling: true,
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

    // Fetch immediately (async), then on interval
    const timeout = setTimeout(fetchStatus, 0);
    intervalRef.current = setInterval(fetchStatus, pollingInterval);

    return () => {
      clearTimeout(timeout);
      stopPolling();
    };
  }, [enabled, calculationId, pollingInterval, fetchStatus, stopPolling]);

  return state;
}
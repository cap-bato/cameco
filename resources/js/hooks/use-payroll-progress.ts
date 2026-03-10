import { useState, useEffect, useRef, useCallback } from 'react';
import { batchStatus } from '@/routes/payroll/calculations';

// ============================================================================
// Type Definitions
// ============================================================================

export interface UsePayrollProgressOptions {
    calculationId: number;
    initialStatus: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    enabled?: boolean; // Default true; allows caller to disable polling
    pollingInterval?: number; // Default 2000ms
    onComplete?: () => void; // Callback when status becomes completed/failed
}

export interface PayrollProgressState {
    progress: number; // 0–100 percentage
    totalJobs: number | null;
    pendingJobs: number | null;
    failedJobs: number | null;
    finished: boolean | null;
    cancelled: boolean | null;
    batchFound: boolean;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    isPolling: boolean;
    error: string | null;
}

interface BatchStatusResponse {
    progress: number;
    total_jobs: number | null;
    pending_jobs: number | null;
    failed_jobs: number | null;
    finished: boolean | null;
    cancelled: boolean | null;
    batch_found: boolean;
    batch_id?: string;
    error?: string;
}

// ============================================================================
// Hook Implementation
// ============================================================================

export function usePayrollProgress(options: UsePayrollProgressOptions): PayrollProgressState {
    const {
        calculationId,
        initialStatus,
        enabled = true,
        pollingInterval = 2000,
        onComplete,
    } = options;

    // State
    const [state, setState] = useState<PayrollProgressState>({
        progress: 0,
        totalJobs: null,
        pendingJobs: null,
        failedJobs: null,
        finished: null,
        cancelled: null,
        batchFound: false,
        status: initialStatus,
        isPolling: false,
        error: null,
    });

    // Refs to track polling state
    const intervalRef = useRef<NodeJS.Timeout | null>(null);
    const consecutiveFailuresRef = useRef(0);
    const hasCalledOnCompleteRef = useRef(false);

    // Stop polling helper (defined first to avoid circular dependency)
    const stopPolling = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
            setState(prev => ({ ...prev, isPolling: false }));
        }
    }, []);

    // Fetch batch status from API
    const fetchBatchStatus = useCallback(async () => {
        try {
            const url = batchStatus.url({ id: calculationId });
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // Include cookies for auth
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data: BatchStatusResponse = await response.json();

            // Reset failure counter on success
            consecutiveFailuresRef.current = 0;

            // Determine status from response
            let newStatus: PayrollProgressState['status'] = state.status;
            if (data.finished === true || data.progress >= 100) {
                newStatus = 'completed';
            } else if (data.cancelled === true) {
                newStatus = 'cancelled';
            } else if (data.progress > 0 && data.progress < 100) {
                newStatus = 'processing';
            }

            // Update state
            setState({
                progress: data.progress || 0,
                totalJobs: data.total_jobs,
                pendingJobs: data.pending_jobs,
                failedJobs: data.failed_jobs,
                finished: data.finished,
                cancelled: data.cancelled,
                batchFound: data.batch_found,
                status: newStatus,
                isPolling: true,
                error: null,
            });

            // Check if calculation is complete
            if ((data.finished === true || data.cancelled === true || data.progress >= 100) && 
                onComplete && 
                !hasCalledOnCompleteRef.current) {
                hasCalledOnCompleteRef.current = true;
                onComplete();
                stopPolling();
            }

        } catch (error) {
            consecutiveFailuresRef.current += 1;
            console.error('Failed to fetch batch status:', error);

            // Update error state but continue polling (transient error tolerance)
            setState(prev => ({
                ...prev,
                error: error instanceof Error ? error.message : 'Failed to fetch batch status',
                isPolling: true,
            }));

            // Stop polling after 3 consecutive failures
            if (consecutiveFailuresRef.current >= 3) {
                console.error('Stopping polling after 3 consecutive failures');
                setState(prev => ({
                    ...prev,
                    error: 'Failed to fetch batch status after 3 attempts. Please refresh the page.',
                    isPolling: false,
                }));
                stopPolling();
            }
        }
    }, [calculationId, onComplete, state.status, stopPolling]);

    // Start polling effect
    useEffect(() => {
        // Don't poll if disabled or not processing status
        if (!enabled || state.status !== 'processing') {
            stopPolling();
            return;
        }

        // Fetch immediately on mount/enable
        fetchBatchStatus();

        // Start interval polling
        intervalRef.current = setInterval(fetchBatchStatus, pollingInterval);

        // Cleanup on unmount or when dependencies change
        return () => {
            stopPolling();
        };
    }, [enabled, state.status, pollingInterval, fetchBatchStatus, stopPolling]);

    return state;
}

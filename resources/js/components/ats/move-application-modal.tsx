import { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import type { Application, ApplicationStatus } from '@/types/ats-pages';

interface MoveApplicationModalProps {
  open: boolean;
  application: Application | null;
  currentStatus: ApplicationStatus;
  targetStatus?: ApplicationStatus | null;
  onConfirm: (newStatus: ApplicationStatus, notes?: string) => void;
  onCancel: () => void;
  isLoading?: boolean;
}

const statusOptions: { value: ApplicationStatus; label: string; color: string }[] = [
  { value: 'submitted',   label: 'Submitted',   color: 'text-blue-600'    },
  { value: 'shortlisted', label: 'Shortlisted', color: 'text-purple-600'  },
  { value: 'interviewed', label: 'Interviewed', color: 'text-yellow-600'  },
  { value: 'offered',     label: 'Offered',     color: 'text-green-600'   },
  { value: 'hired',       label: 'Hired',       color: 'text-emerald-600' },
  { value: 'rejected',    label: 'Rejected',    color: 'text-red-600'     },
  { value: 'withdrawn',   label: 'Withdrawn',   color: 'text-gray-600'    },
];

/**
 * Move Application Modal
 * Confirmation dialog for changing application status.
 *
 * Fix 1 – hydration error: DialogDescription renders a <p>, so we must never
 *          place block-level elements (div, p, ul …) inside it. The candidate
 *          info block has been moved OUTSIDE DialogDescription entirely and
 *          rendered as a plain <div> inside the DialogHeader instead.
 *
 * Fix 2 – JSON response: the parent (HiringPipelineIndex) now calls the
 *          correct route PUT /hr/ats/hiring-pipeline/applications/{id}/move.
 */
export const MoveApplicationModal = ({
  open,
  application,
  currentStatus,
  targetStatus,
  onConfirm,
  onCancel,
  isLoading = false,
}: MoveApplicationModalProps) => {
  const [selectedStatus, setSelectedStatus] = useState<ApplicationStatus | ''>(
    targetStatus || ''
  );
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (!open) {
      setSelectedStatus('');
      setNotes('');
    } else if (targetStatus) {
      setSelectedStatus(targetStatus);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const handleConfirm = () => {
    if (selectedStatus && selectedStatus !== currentStatus) {
      onConfirm(selectedStatus as ApplicationStatus, notes || undefined);
      setSelectedStatus('');
      setNotes('');
    }
  };

  const handleCancel = () => {
    setSelectedStatus('');
    setNotes('');
    onCancel();
  };

  const currentStatusLabel = statusOptions.find((s) => s.value === currentStatus)?.label;

  return (
    <Dialog open={open} onOpenChange={(isOpen: boolean) => !isOpen && handleCancel()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Move Application</DialogTitle>

          {/* ✅ NOT inside DialogDescription — avoids <div> inside <p> hydration error */}
          {application && (
            <div className="mt-2 space-y-1">
              <p className="font-medium text-foreground">
                {application.candidate_name || 'Unknown Candidate'}
              </p>
              <p className="text-sm text-muted-foreground">
                {application.job_title || 'Position'}
              </p>
              <p className="text-xs text-muted-foreground mt-2">
                Current status:{' '}
                <span className="font-semibold capitalize">{currentStatusLabel}</span>
              </p>
            </div>
          )}
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="status-select">New Status</Label>
            <Select
              value={selectedStatus}
              onValueChange={(value) => setSelectedStatus(value as ApplicationStatus)}
              disabled={isLoading}
            >
              <SelectTrigger id="status-select">
                <SelectValue placeholder="Select new status..." />
              </SelectTrigger>
              <SelectContent>
                {statusOptions
                  .filter((s) => s.value !== currentStatus)
                  .map((status) => (
                    <SelectItem key={status.value} value={status.value}>
                      <span className={status.color}>{status.label}</span>
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">Notes (Optional)</Label>
            <Textarea
              id="notes"
              placeholder="Add a note about this status change..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="min-h-[80px] resize-none"
              disabled={isLoading}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" disabled={isLoading} onClick={handleCancel}>
            Cancel
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={!selectedStatus || selectedStatus === currentStatus || isLoading}
            className="bg-blue-600 hover:bg-blue-700"
          >
            {isLoading ? 'Moving...' : 'Move Application'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
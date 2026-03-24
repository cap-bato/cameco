import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Eye, Trash2, MoreHorizontal, ArrowRight, AlertCircle } from 'lucide-react';
import { MoveApplicationModal } from './move-application-modal';
import type { Application, ApplicationStatus } from '@/types/ats-pages';

interface ApplicationListActionsProps {
  application: Application;
  onView?: (app: Application) => void;
  onChangeStatus?: (app: Application, newStatus: ApplicationStatus, notes?: string) => void;
  onDelete?: (app: Application) => void;
  isCompact?: boolean;
  isLoading?: boolean;
}

export function ApplicationListActions({
  application,
  onView,
  onChangeStatus,
  onDelete,
  isCompact = false,
  isLoading = false,
}: ApplicationListActionsProps) {
  const [moveModalOpen, setMoveModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleConfirmStatusChange = (newStatus: ApplicationStatus, notes?: string) => {
    if (onChangeStatus) {
      onChangeStatus(application, newStatus, notes);
      setMoveModalOpen(false);
    }
  };

  const handleConfirmDelete = async () => {
    setIsDeleting(true);
    try {
      if (onDelete) {
        onDelete(application);
      }
      setDeleteDialogOpen(false);
    } finally {
      setIsDeleting(false);
    }
  };

  // ─── Shared modals (used by both compact and expanded views) ────────────────
  const modals = (
    <>
      <MoveApplicationModal
        open={moveModalOpen}
        application={application}
        currentStatus={application.status}
        onConfirm={handleConfirmStatusChange}
        onCancel={() => setMoveModalOpen(false)}
        isLoading={isLoading}
      />

      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <AlertCircle className="h-5 w-5 text-red-600" />
              Delete Application
            </DialogTitle>
            <DialogDescription>
              Are you sure you want to delete the application from{' '}
              <span className="font-semibold">{application.candidate_name || 'Unknown'}</span>?
              This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteDialogOpen(false)}
              disabled={isDeleting}
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={handleConfirmDelete}
              disabled={isDeleting}
            >
              {isDeleting ? 'Deleting...' : 'Delete'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );

  // ─── Compact dropdown view ───────────────────────────────────────────────────
  if (isCompact) {
    return (
      <>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="sm"
              className="h-8 w-8 p-0"
              disabled={isLoading || isDeleting}
            >
              <MoreHorizontal className="h-4 w-4" />
              <span className="sr-only">Open menu</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-48">
            {onView && (
              <>
                <DropdownMenuItem
                  onClick={() => onView(application)}
                  className="cursor-pointer"
                  disabled={isLoading || isDeleting}
                >
                  <Eye className="h-4 w-4 mr-2" />
                  View Details
                </DropdownMenuItem>
                <DropdownMenuSeparator />
              </>
            )}
            {onChangeStatus && (
              <DropdownMenuItem
                onClick={() => setMoveModalOpen(true)}
                className="cursor-pointer"
                disabled={isLoading || isDeleting}
              >
                <ArrowRight className="h-4 w-4 mr-2" />
                Change Status
              </DropdownMenuItem>
            )}
            {onDelete && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => setDeleteDialogOpen(true)}
                  className="cursor-pointer text-red-600 focus:text-red-600"
                  disabled={isLoading || isDeleting}
                >
                  <Trash2 className="h-4 w-4 mr-2" />
                  Delete
                </DropdownMenuItem>
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
        {modals}
      </>
    );
  }

  // ─── Expanded button group view ──────────────────────────────────────────────
  return (
    <>
      <div className="flex items-center justify-end gap-2">
        {onView && (
          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 hover:bg-blue-50 hover:text-blue-600"
            onClick={() => onView(application)}
            title="View details"
            disabled={isLoading || isDeleting}
          >
            <Eye className="h-4 w-4" />
            <span className="sr-only">View details</span>
          </Button>
        )}
        {onChangeStatus && (
          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 hover:bg-purple-50 hover:text-purple-600"
            onClick={() => setMoveModalOpen(true)}
            title="Change status"
            disabled={isLoading || isDeleting}
          >
            <ArrowRight className="h-4 w-4" />
            <span className="sr-only">Change status</span>
          </Button>
        )}
        {onDelete && (
          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600 text-red-600"
            onClick={() => setDeleteDialogOpen(true)}
            title="Delete application"
            disabled={isLoading || isDeleting}
          >
            <Trash2 className="h-4 w-4" />
            <span className="sr-only">Delete application</span>
          </Button>
        )}
      </div>
      {modals}
    </>
  );
}
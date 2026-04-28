import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useToast } from '@/hooks/use-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Plus } from 'lucide-react';
import { CandidateTable } from '@/components/ats/candidate-table';
import { AddCandidateModal, type CandidateFormData } from '@/components/ats/add-candidate-modal';
import { AddNoteModal } from '@/components/ats/add-note-modal';
import { CandidateFilters } from '@/components/ats/candidate-filters';
import { PermissionGate } from '@/components/permission-gate';
import type { PageProps } from '@inertiajs/core';
import type { Candidate, CandidateSummary, CandidateFilters as CandidateFiltersType } from '@/types/ats-pages';
import axios from 'axios';
interface CandidatesIndexProps extends PageProps {
  candidates: Candidate[];
  statistics: CandidateSummary;
  filters: CandidateFiltersType;
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'HR', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Candidates', href: '/hr/ats/candidates' },
];

/**
 * Candidates Index Page
 * Displays all candidates with filters, search, and CRUD operations
 */
export default function CandidatesIndex({
  candidates,
  statistics,
  filters: initialFilters,
}: CandidatesIndexProps) {
  const { toast } = useToast();
  const [searchQuery, setSearchQuery] = useState(initialFilters?.search || '');
  const [sourceFilter, setSourceFilter] = useState(initialFilters?.source || '');
  const [statusFilter, setStatusFilter] = useState(initialFilters?.status || '');
  const [actionCandidate, setActionCandidate] = useState<Candidate | undefined>(undefined);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [isDeleteLoading, setIsDeleteLoading] = useState(false);
  const [isAddCandidateModalOpen, setIsAddCandidateModalOpen] = useState(false);
  const [isAddNoteModalOpen, setIsAddNoteModalOpen] = useState(false);
  const [noteCandidate, setNoteCandidate] = useState<Candidate | undefined>(undefined);

  /**
   * Filter candidates based on search query, source, and status
   */
  const getFilteredCandidates = () => {
    // Guard: ensure candidates is an array
    if (!Array.isArray(candidates)) {
      return [];
    }

    return candidates.filter((candidate) => {
      // Filter by search query (case-insensitive name search)
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const fullName = `${candidate.first_name} ${candidate.last_name}`.toLowerCase();
        if (!fullName.includes(query)) {
          return false;
        }
      }

      // Filter by source
      if (sourceFilter && candidate.source !== sourceFilter) {
        return false;
      }

      // Filter by status
      if (statusFilter && candidate.status !== statusFilter) {
        return false;
      }

      return true;
    });
  };

  const filteredCandidates = getFilteredCandidates();

  const handleFilterChange = () => {
    // Applied filters are now synchronized with state via hooks
    // Real-time filtering happens in getFilteredCandidates()
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setSourceFilter('');
    setStatusFilter('');
  };

  const handleDeleteClick = (candidate: Candidate) => {
    console.log('Delete click - candidate object:', candidate);
    console.log('Delete click - candidate.id:', candidate.id);
    setActionCandidate(candidate);
    setIsDeleteDialogOpen(true);
  };

const handleConfirmDelete = async () => {
  console.log('handleConfirmDelete - full actionCandidate object:', JSON.stringify(actionCandidate, null, 2));
  console.log('handleConfirmDelete - actionCandidate.id:', actionCandidate?.id);
  console.log('handleConfirmDelete - typeof actionCandidate.id:', typeof actionCandidate?.id);
  console.log('handleConfirmDelete - !actionCandidate:', !actionCandidate);
  console.log('handleConfirmDelete - !actionCandidate.id:', !actionCandidate?.id);
  
  if (!actionCandidate || !actionCandidate.id) {
    console.warn('Validation failed: actionCandidate or id is missing');
    toast({
      title: 'Error',
      description: 'Invalid candidate. Please try again.',
      variant: 'destructive',
    });
    setIsDeleteDialogOpen(false);
    return;
  }

  setIsDeleteLoading(true);

  try {
    const deleteUrl = `/hr/ats/candidates/${actionCandidate.id}`;
    console.log('Making DELETE request to:', deleteUrl);
    await axios.delete(deleteUrl);

    toast({
      title: 'Success',
      description: `Candidate ${actionCandidate.first_name} ${actionCandidate.last_name} deleted successfully`,
    });

    // Close dialog immediately
    setIsDeleteDialogOpen(false);

    // Refresh frontend list after toast displays
    setTimeout(() => window.location.reload(), 2000);

  } catch (error: any) {
    // Suppress detailed error logging for handled errors (404, 405)
    if (error.response?.status && ![404, 405].includes(error.response.status)) {
      console.error("Error deleting candidate:", error.response?.data || error);
    }
    
    // Determine error message
    let errorMessage = 'Failed to delete candidate.';
    if (error.response?.status === 404) {
      errorMessage = 'Candidate not found. It may have been already deleted.';
    } else if (error.response?.status === 405) {
      errorMessage = 'Unable to delete candidate. Please refresh and try again.';
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }

    toast({
      title: 'Error',
      description: errorMessage,
      variant: 'destructive',
    });
    
    // Close dialog after showing error
    setIsDeleteDialogOpen(false);
  } finally {
    setIsDeleteLoading(false);
    setActionCandidate(undefined);
  }
};

  const handleAddCandidateClick = () => {
    setIsAddCandidateModalOpen(true);
  };

const handleAddCandidateSubmit = async (data: CandidateFormData) => {
  try {
    const formData = new FormData();

    Object.keys(data).forEach((key) => {
      const value = (data as any)[key];
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    });

    await axios.post('/hr/ats/candidates', formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    toast({
      title: 'Success',
      description: 'Candidate created successfully!',
    });
    setIsAddCandidateModalOpen(false);
    // Refresh after toast displays
    setTimeout(() => window.location.reload(), 2000);

  } catch (error: any) {
    // If it's a validation error (422), let the modal handle it
    if (error.response?.status === 422 && error.response?.data?.errors) {
      throw error;
    }
    
    // For other errors, show a toast
    const message =
      error.response?.data?.message ||
      error.message ||
      "An unexpected error occurred. Please try again.";

    toast({
      title: 'Error',
      description: message,
      variant: 'destructive',
    });
    
    throw error;
  }
};




  const handleAddNoteClick = (candidate: Candidate) => {
    setNoteCandidate(candidate);
    setIsAddNoteModalOpen(true);
  };

const handleAddNoteSubmit = async (noteData: { note: string; is_private: boolean }) => {
  if (!noteCandidate) return;

  try {
    await axios.post(`/hr/ats/candidates/${noteCandidate.id}/notes`, noteData);

    toast({
      title: 'Success',
      description: 'Note added successfully!',
    });
    setIsAddNoteModalOpen(false);
    // Reload after toast displays
    setTimeout(() => window.location.reload(), 2000);
  } catch (error: any) {
    console.error(error);
    toast({
      title: 'Error',
      description: error.response?.data?.message || 'Something went wrong.',
      variant: 'destructive',
    });
  }
};

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Candidates" />

      <div className="space-y-6 p-6">
        {/* Page Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Candidates</h1>
            <p className="text-muted-foreground mt-2">
              Manage your candidates and track their application progress
            </p>
          </div>
          <PermissionGate permission="hr.ats.candidates.create">
            <div>
              <Button className="gap-2" onClick={handleAddCandidateClick}>
                <Plus className="h-4 w-4" />
                Add Candidate
              </Button>
            </div>
          </PermissionGate>
        </div>

        {/* Statistics Cards */}
        {statistics && (
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Total</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{statistics.total_candidates}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">New</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-blue-600">{statistics.new_candidates}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">In Process</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-yellow-600">
                  {statistics.in_process}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Interviewed</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-purple-600">
                  {statistics.interviewed}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Hired</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-600">
                  {statistics.hired}
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Filters */}
        <CandidateFilters
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          sourceFilter={sourceFilter}
          onSourceChange={setSourceFilter}
          statusFilter={statusFilter}
          onStatusChange={setStatusFilter}
          onApplyFilters={handleFilterChange}
          onResetFilters={handleResetFilters}
        />

        {/* Candidates Table */}
        {filteredCandidates.length > 0 ? (
          <CandidateTable
            candidates={filteredCandidates}
            onViewClick={() => {
              // Navigation handled by Link in component
            }}
            onAddNoteClick={handleAddNoteClick}
            onDeleteClick={handleDeleteClick}
          />
        ) : (
          <div className="bg-card rounded-lg border p-8 text-center">
            <p className="text-muted-foreground">No candidates found</p>
            <Button variant="outline" className="mt-4" onClick={() => setIsAddCandidateModalOpen(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Add your first candidate
            </Button>
          </div>
        )}
      </div>

      {/* Delete Confirmation Dialog */}
      <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Candidate</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <p className="font-medium text-foreground">
              {actionCandidate?.first_name} {actionCandidate?.last_name}
            </p>
            <p className="text-muted-foreground">
              Deleting this candidate is permanent and cannot be undone. All related applications will be archived.
            </p>
            <div className="bg-destructive/10 border border-destructive/30 rounded-md p-3 mt-4">
              <p className="text-sm text-destructive font-medium">
                ⚠️ This action cannot be undone
              </p>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setIsDeleteDialogOpen(false)}
              disabled={isDeleteLoading}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleConfirmDelete}
              disabled={isDeleteLoading}
            >
              {isDeleteLoading ? 'Deleting...' : 'Delete'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add Candidate Modal */}
      <AddCandidateModal
        isOpen={isAddCandidateModalOpen}
        onClose={() => setIsAddCandidateModalOpen(false)}
        onSubmit={handleAddCandidateSubmit}
      />

      {/* Add Note Modal */}
      <AddNoteModal
        isOpen={isAddNoteModalOpen}
        candidateName={noteCandidate ? `${noteCandidate.first_name} ${noteCandidate.last_name}` : undefined}
        onClose={() => setIsAddNoteModalOpen(false)}
        onSubmit={handleAddNoteSubmit}
      />
    </AppLayout>
  );
}

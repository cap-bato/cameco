import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
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
    setActionCandidate(candidate);
    setIsDeleteDialogOpen(true);
  };

const handleConfirmDelete = async () => {
  if (!actionCandidate) return;

  setIsDeleteLoading(true);

  try {
    await axios.delete(`/hr/ats/candidates/${actionCandidate.id}`);

    alert(`Candidate ${actionCandidate.first_name} ${actionCandidate.last_name} deleted successfully`);

    // Refresh frontend list
    window.location.reload();

  } catch (error: any) {
    console.error("Error deleting candidate:", error.response?.data || error);
    alert("Failed to delete candidate. Check console for details.");
  } finally {
    setIsDeleteLoading(false);
    setIsDeleteDialogOpen(false);
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

    // IMPORTANT: if resume is a File, append separately
    if (data.resume instanceof File) {
      formData.append("resume", data.resume);
    }

    await axios.post('/hr/ats/candidates', formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    alert('Candidate created successfully!');
    window.location.reload();
    setIsAddCandidateModalOpen(false);

  } catch (error: any) {
    const message =
      error.response?.data?.message ||
      "An unexpected error occurred. Please try again.";

    alert(message);
    console.error("Error submitting candidate:", error.response?.data || error.message);
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

    alert("Note added successfully!");
    window.location.reload(); // reload to show notes
    setIsAddNoteModalOpen(false);
  } catch (error: any) {
    console.error(error);
    alert(error.response?.data?.message || "Something went wrong.");
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

import React, { useState, useEffect } from 'react';
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
import { ApplicationTable } from '@/components/ats/application-table';
import { ApplicationFilters } from '@/components/ats/application-filters';
import { BulkActionsCard } from '@/components/ats/bulk-actions-card';
import type { PageProps } from '@inertiajs/core';
import type { Application, ApplicationSummary, ApplicationFilters as ApplicationFiltersType } from '@/types/ats-pages';
import axios from 'axios';

interface ApplicationsIndexProps extends PageProps {
  applications: Application[];
  statistics: ApplicationSummary;
  filters: ApplicationFiltersType;
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Applications', href: '/applications' },
];

export default function ApplicationsIndex({
  applications,
  statistics,
  filters: initialFilters,
}: ApplicationsIndexProps) {
  const [apps, setApps] = useState<Application[]>(Array.isArray(applications) ? applications : []);
  const [statusFilter, setStatusFilter] = useState(initialFilters?.status || '');
  const [jobFilter, setJobFilter] = useState(initialFilters?.job || '');
  const [scoreFromFilter, setScoreFromFilter] = useState(initialFilters?.scoreFrom?.toString() || '');
  const [scoreToFilter, setScoreToFilter] = useState(initialFilters?.scoreTo?.toString() || '');
  const [actionApplication, setActionApplication] = useState<Application | undefined>(undefined);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [isDeleteLoading, setIsDeleteLoading] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [isBulkLoading, setIsBulkLoading] = useState(false);
  const [bulkAction, setBulkAction] = useState<string | null>(null);

  // Schedule Interview
  const [interviewApplication, setInterviewApplication] = useState<Application | undefined>(undefined);
  const [isInterviewModalOpen, setIsInterviewModalOpen] = useState(false);
  const [interviewFormData, setInterviewFormData] = useState({
    scheduled_date: '',
    scheduled_time: '',
    duration_minutes: 60,
    location_type: 'office' as 'office' | 'video_call' | 'phone',
  });

  useEffect(() => {
    if (Array.isArray(applications)) {
      setApps(applications);
    }
  }, [applications]);

  const getFilteredApplications = () => {
    return apps.filter((application) => {
      if (statusFilter && application.status !== statusFilter) return false;

      if (jobFilter && typeof jobFilter === 'string' && jobFilter.trim()) {
        const query = jobFilter.toLowerCase();
        const jobTitle = application.job_title || '';
        if (!jobTitle.toLowerCase().includes(query)) return false;
      }

      if (application.score !== null && application.score !== undefined) {
        const scoreFrom = scoreFromFilter ? parseInt(scoreFromFilter) : null;
        const scoreTo = scoreToFilter ? parseInt(scoreToFilter) : null;
        if (scoreFrom && application.score < scoreFrom) return false;
        if (scoreTo && application.score > scoreTo) return false;
      }

      return true;
    });
  };

  const filteredApplications = getFilteredApplications();

  const handleResetFilters = () => {
    setStatusFilter('');
    setJobFilter('');
    setScoreFromFilter('');
    setScoreToFilter('');
  };

  // Shortlist
  const handleShortlistClick = async (application: Application) => {
    try {
      setApps((prev) => prev.map((a) => (a.id === application.id ? { ...a, status: 'shortlisted' } : a)));
      await axios.post(`/hr/ats/applications/${application.id}/shortlist`);
    } catch (err) {
      console.error('Failed to shortlist:', err);
      setApps((prev) => prev.map((a) => (a.id === application.id ? application : a)));
    }
  };

  // Reject
  const handleRejectClick = (application: Application) => {
    setActionApplication(application);
    setIsDeleteDialogOpen(true);
  };

  const handleConfirmReject = async () => {
    if (!actionApplication) return;
    setIsDeleteLoading(true);

    try {
      await axios.post(`/hr/ats/applications/${actionApplication.id}/reject`, { reason: 'Rejected via UI' });

      setApps((prev) =>
        prev.map((a) =>
          a.id === actionApplication.id ? { ...a, status: 'rejected' } : a
        )
      );
    } catch (err) {
      console.error('Failed to reject application:', err);
    } finally {
      setIsDeleteLoading(false);
      setIsDeleteDialogOpen(false);
      setActionApplication(undefined);
    }
  };

  // Open Schedule Interview Modal
  const handleScheduleInterviewClick = (application: Application) => {
    setInterviewApplication(application);
    setIsInterviewModalOpen(true);
  };

  // Submit Schedule Interview
  const handleScheduleInterviewSubmit = async () => {
    if (!interviewApplication) return;

    try {
      await axios.post(`/hr/ats/applications/${interviewApplication.id}/schedule-interview`, {
        scheduled_date: interviewFormData.scheduled_date,
        scheduled_time: interviewFormData.scheduled_time,
        duration_minutes: interviewFormData.duration_minutes,
        location_type: interviewFormData.location_type,
        interviewer_name: 'HR Manager',
      });

      // Optimistic UI update
      setApps((prev) =>
        prev.map((a) =>
          a.id === interviewApplication.id ? { ...a, status: 'interviewed' } : a
        )
      );

      setIsInterviewModalOpen(false);
      setInterviewApplication(undefined);
      setInterviewFormData({
        scheduled_date: '',
        scheduled_time: '',
        duration_minutes: 60,
        location_type: 'office',
      });
    } catch (err) {
      console.error('Failed to schedule interview:', err);
    }
  };

  // Bulk Actions
  const toggleSelectApplication = (id: number) => {
    setSelectedIds((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(id)) newSet.delete(id);
      else newSet.add(id);
      return newSet;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === filteredApplications.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(filteredApplications.map((a) => a.id)));
    }
  };

  const handleBulkShortlist = async () => {
    if (!selectedIds.size) return;
    setIsBulkLoading(true);
    setBulkAction('shortlist');
    try {
      setApps((prev) => prev.map((a) => (selectedIds.has(a.id) ? { ...a, status: 'shortlisted' } : a)));
      await Promise.all(Array.from(selectedIds).map(id => axios.post(`/hr/ats/applications/${id}/shortlist`)));
      setSelectedIds(new Set());
    } catch (err) {
      console.error('Failed to bulk shortlist:', err);
    } finally {
      setIsBulkLoading(false);
      setBulkAction(null);
    }
  };

  const handleBulkReject = async () => {
    if (!selectedIds.size) return;
    setIsBulkLoading(true);
    setBulkAction('reject');
    try {
      setApps((prev) => prev.map((a) => (selectedIds.has(a.id) ? { ...a, status: 'rejected' } : a)));
      await Promise.all(Array.from(selectedIds).map(id => axios.post(`/hr/ats/applications/${id}/reject`, { reason: 'Rejected via UI' })));
      setSelectedIds(new Set());
    } catch (err) {
      console.error('Failed to bulk reject:', err);
    } finally {
      setIsBulkLoading(false);
      setBulkAction(null);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Applications" />

      <div className="space-y-6 p-6">
        <div className="mb-8">
          <h1 className="text-3xl font-bold tracking-tight">Applications</h1>
          <p className="text-muted-foreground mt-2">Manage and track job applications</p>
        </div>

        {/* Summary Cards */}
        {statistics && (
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            {['total_applications','submitted','shortlisted','interviewed','offered'].map((key) => (
              <Card key={key}>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium">{key.replace('_',' ').toUpperCase()}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-blue-600">{statistics[key as keyof ApplicationSummary]}</div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {/* Bulk Actions */}
        <BulkActionsCard
          selectedCount={selectedIds.size}
          actions={[
            {
              label: 'Shortlist All',
              onClick: handleBulkShortlist,
              disabled: isBulkLoading,
              loadingText: 'Shortlisting...',
              isLoading: isBulkLoading && bulkAction === 'shortlist',
            },
            {
              label: 'Reject All',
              variant: 'destructive',
              onClick: handleBulkReject,
              disabled: isBulkLoading,
              loadingText: 'Rejecting...',
              isLoading: isBulkLoading && bulkAction === 'reject',
            },
          ]}
          onClear={() => setSelectedIds(new Set())}
          isLoading={isBulkLoading}
        />

        {/* Filters */}
        <ApplicationFilters
          statusFilter={statusFilter}
          onStatusChange={setStatusFilter}
          jobFilter={String(jobFilter)}
          onJobChange={setJobFilter}
          scoreFromFilter={scoreFromFilter}
          onScoreFromChange={setScoreFromFilter}
          scoreToFilter={scoreToFilter}
          onScoreToChange={setScoreToFilter}
          onApplyFilters={() => {}}
          onResetFilters={handleResetFilters}
        />

        {/* Applications Table */}
        {filteredApplications.length > 0 ? (
          <ApplicationTable
            applications={filteredApplications}
            selectedIds={selectedIds}
            onSelectApplication={toggleSelectApplication}
            onSelectAll={toggleSelectAll}
            onViewClick={(application) => window.location.href = `/hr/ats/applications/${application.id}`}
            onShortlistClick={handleShortlistClick}
            onRejectClick={handleRejectClick}
            onScheduleInterviewClick={handleScheduleInterviewClick}
          />
        ) : (
          <div className="bg-card rounded-lg border p-8 text-center">
            <p className="text-muted-foreground">No applications found</p>
          </div>
        )}
      </div>

      {/* Reject Dialog */}
      <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject Application</DialogTitle>
          </DialogHeader>
          <p className="py-4 text-sm text-muted-foreground">
            Are you sure you want to reject {actionApplication?.candidate_name || 'this candidate'}? This action cannot be undone.
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>Cancel</Button>
            <Button type="button" variant="destructive" onClick={handleConfirmReject} disabled={isDeleteLoading}>
              {isDeleteLoading ? 'Rejecting...' : 'Reject'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Schedule Interview Dialog */}
      <Dialog open={isInterviewModalOpen} onOpenChange={setIsInterviewModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Schedule Interview</DialogTitle>
          </DialogHeader>

          <p className="text-sm text-muted-foreground mb-4">
            Candidate: <span className="font-semibold">{interviewApplication?.candidate_name}</span>
          </p>

          <div className="space-y-4">
            <div>
              <label className="text-sm font-medium">Date</label>
              <input type="date" className="mt-1 w-full border px-3 py-2 rounded" value={interviewFormData.scheduled_date} onChange={(e) => setInterviewFormData({...interviewFormData, scheduled_date: e.target.value})}/>
            </div>
            <div>
              <label className="text-sm font-medium">Time</label>
              <input type="time" className="mt-1 w-full border px-3 py-2 rounded" value={interviewFormData.scheduled_time} onChange={(e) => setInterviewFormData({...interviewFormData, scheduled_time: e.target.value})}/>
            </div>
            <div>
              <label className="text-sm font-medium">Duration (minutes)</label>
              <input type="number" className="mt-1 w-full border px-3 py-2 rounded" min={15} step={15} value={interviewFormData.duration_minutes} onChange={(e) => setInterviewFormData({...interviewFormData, duration_minutes: parseInt(e.target.value)||60})}/>
            </div>
            <div>
              <label className="text-sm font-medium">Location</label>
              <select className="mt-1 w-full border px-3 py-2 rounded" value={interviewFormData.location_type} onChange={(e) => setInterviewFormData({...interviewFormData, location_type: e.target.value as 'office' | 'video_call' | 'phone'})}>
                <option value="office">Office</option>
                <option value="video_call">Video Call</option>
                <option value="phone">Phone</option>
              </select>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setIsInterviewModalOpen(false)}>Cancel</Button>
            <Button type="button" onClick={handleScheduleInterviewSubmit} disabled={!interviewFormData.scheduled_date || !interviewFormData.scheduled_time}>
              Schedule Interview
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}

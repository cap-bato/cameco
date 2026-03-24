import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Plus, Edit, Trash2, Globe, Lock, Clock, Facebook, CheckCircle, AlertTriangle } from 'lucide-react';
import { JobStatusBadge } from '@/components/ats/job-status-badge';
import { JobPostingFilters } from '@/components/ats/job-posting-filters';
import { Badge } from '@/components/ui/badge';
import { FacebookPreviewModal } from '@/components/ats/FacebookPreviewModal';
import { FacebookLogsModal } from '@/components/ats/FacebookLogsModal';
import { JobPostingCreateEditModal } from './CreateEditModal';
import type { PageProps } from '@inertiajs/core';
import type { JobPosting, JobPostingFormData, JobPostingFilters as JobPostingFiltersType, JobPostingSummary } from '@/types/ats-pages';

interface Department {
  id: number;
  name: string;
}

interface JobPostingsIndexProps extends PageProps {
  job_postings: JobPosting[];
  statistics: JobPostingSummary;
  filters: JobPostingFiltersType;
  departments: Department[];
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'HR', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Job Postings', href: '/hr/ats/job-postings' },
];

type ActionType = 'publish' | 'close' | 'delete';

const actionConfig: Record<ActionType, {
  title: string;
  description: string;
  confirmLabel: string;
  confirmVariant: 'default' | 'secondary' | 'destructive';
  icon: React.ReactNode;
  iconBg: string;
}> = {
  publish: {
    title: 'Publish Job Posting',
    description: 'Publishing this job posting will make it visible to all candidates. They will be able to apply for this position.',
    confirmLabel: 'Publish',
    confirmVariant: 'default',
    icon: <Globe className="h-6 w-6 text-green-600" />,
    iconBg: 'bg-green-50',
  },
  close: {
    title: 'Close Job Posting',
    description: 'Closing this job posting will prevent new applications. Existing applications will remain in the system.',
    confirmLabel: 'Close Posting',
    confirmVariant: 'secondary',
    icon: <Lock className="h-6 w-6 text-orange-600" />,
    iconBg: 'bg-orange-50',
  },
  delete: {
    title: 'Delete Job Posting',
    description: 'This will permanently delete the job posting. All related data will be removed and this action cannot be undone.',
    confirmLabel: 'Delete',
    confirmVariant: 'destructive',
    icon: <Trash2 className="h-6 w-6 text-red-600" />,
    iconBg: 'bg-red-50',
  },
};

export default function JobPostingsIndex({
  job_postings,
  statistics,
  departments,
  filters: initialFilters,
}: JobPostingsIndexProps) {
  const { props } = usePage();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingJob, setEditingJob] = useState<JobPosting | undefined>(undefined);
  const [appliedFilters, setAppliedFilters] = useState<JobPostingFiltersType>(initialFilters || {});
  const [actionJob, setActionJob] = useState<JobPosting | undefined>(undefined);
  const [actionType, setActionType] = useState<ActionType | null>(null);
  const [isActionLoading, setIsActionLoading] = useState(false);
  const [isPreviewOpen, setIsPreviewOpen] = useState(false);
  const [previewJob, setPreviewJob] = useState<JobPosting | undefined>(undefined);
  const [isLogsOpen, setIsLogsOpen] = useState(false);
  const [logsJob, setLogsJob] = useState<JobPosting | undefined>(undefined);

  // ── Flash toasts ────────────────────────────────────────────────────────────
  useEffect(() => {
    const flash = props.flash as Record<string, string> | undefined;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [props.flash]);

  // ── Create / Edit ────────────────────────────────────────────────────────────
  const handleCreateClick = () => {
    setEditingJob(undefined);
    setIsModalOpen(true);
  };

  const handleEditClick = (job: JobPosting) => {
    setEditingJob(job);
    setIsModalOpen(true);
  };

  const handleModalClose = () => {
    setEditingJob(undefined);
    setIsModalOpen(false);
  };

  const handleFormSubmit = (data: JobPostingFormData) => {
    if (editingJob?.id) {
      router.put(`/hr/ats/job-postings/${editingJob.id}`, data, {
        onSuccess: () => handleModalClose(),
        onError: () => toast.error('Failed to update job posting. Please try again.'),
      });
    } else {
      router.post('/hr/ats/job-postings', data, {
        onSuccess: () => handleModalClose(),
        onError: () => toast.error('Failed to create job posting. Please try again.'),
      });
    }
  };

  // ── Confirm Action (publish / close / delete) ────────────────────────────────
  const handleConfirmAction = () => {
    if (!actionJob || !actionType) return;

    setIsActionLoading(true);

    const routes: Record<ActionType, { method: 'post' | 'delete'; url: string }> = {
      publish: { method: 'post',   url: `/hr/ats/job-postings/${actionJob.id}/publish` },
      close:   { method: 'post',   url: `/hr/ats/job-postings/${actionJob.id}/close`   },
      delete:  { method: 'delete', url: `/hr/ats/job-postings/${actionJob.id}`          },
    };

    const { method, url } = routes[actionType];

    const opts = {
      preserveScroll: true,
      onSuccess: () => {
        setIsActionLoading(false);
        setActionJob(undefined);
        setActionType(null);
      },
      onError: () => {
        setIsActionLoading(false);
        toast.error(`Failed to ${actionType} job posting. Please try again.`);
      },
    };

    if (method === 'delete') {
      router.delete(url, opts);
    } else {
      router.post(url, {}, opts);
    }
  };

  const handleCancelAction = () => {
    if (isActionLoading) return;
    setActionJob(undefined);
    setActionType(null);
  };

  // ── Facebook ─────────────────────────────────────────────────────────────────
  const handlePostToFacebook = (job: JobPosting) => {
    if (job.facebook_post_id) {
      toast.error('This job has already been posted to Facebook.');
      return;
    }
    setPreviewJob(job);
    setIsPreviewOpen(true);
  };

  const handleConfirmFacebookPost = async () => {
    if (!previewJob) return;
    router.post(`/hr/ats/job-postings/${previewJob.id}/post-to-facebook`, {}, {
      onSuccess: () => {
        setIsPreviewOpen(false);
        setPreviewJob(undefined);
      },
      onError: () => {
        toast.error('Failed to post to Facebook.');
        setIsPreviewOpen(false);
      },
    });
  };

  const getStatusIcon = (status: string) => {
    if (status === 'open')   return <Globe className="h-4 w-4" />;
    if (status === 'closed') return <Lock  className="h-4 w-4" />;
    if (status === 'draft')  return <Clock className="h-4 w-4" />;
    return null;
  };

  const config = actionType ? actionConfig[actionType] : null;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Job Postings" />

      <div className="space-y-6 p-6">

        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Job Postings</h1>
            <p className="text-muted-foreground mt-2">Manage your job postings and track applications</p>
          </div>
          <Button onClick={handleCreateClick} className="gap-2">
            <Plus className="h-4 w-4" />
            Create Job Posting
          </Button>
        </div>

        {/* Statistics */}
        {statistics && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            {[
              { label: 'Total Jobs',   value: statistics.total_jobs,  color: ''               },
              { label: 'Open',         value: statistics.open_jobs,   color: 'text-green-600' },
              { label: 'Closed',       value: statistics.closed_jobs, color: 'text-gray-600'  },
              { label: 'Draft',        value: statistics.draft_jobs,  color: 'text-blue-600'  },
            ].map(({ label, value, color }) => (
              <Card key={label}>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium">{label}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className={`text-2xl font-bold ${color}`}>{value}</div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {/* Filters */}
        <div className="bg-card rounded-lg border p-4">
          <JobPostingFilters
            filters={appliedFilters}
            departments={departments}
            onFilterChange={setAppliedFilters}
          />
        </div>

        {/* Job Postings Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {job_postings.length > 0 ? (
            job_postings.map((job) => (
              <Card key={job.id} className="hover:shadow-lg transition-shadow">
                <CardHeader>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                      <CardTitle className="text-lg line-clamp-2">{job.title}</CardTitle>
                      <p className="text-sm text-muted-foreground mt-1">
                        {job.department_name || `Dept #${job.department_id}`}
                      </p>
                    </div>
                    <div className="flex flex-col gap-2">
                      <JobStatusBadge status={job.status} />
                      {job.facebook_post_id && (
                        <Badge variant="secondary" className="gap-1">
                          <Facebook className="h-3 w-3" />
                          Posted to Facebook
                        </Badge>
                      )}
                    </div>
                  </div>
                </CardHeader>

                <CardContent className="space-y-4">
                  <p className="text-sm line-clamp-3 text-muted-foreground">{job.description}</p>

                  <div className="flex items-center gap-4 text-sm text-muted-foreground border-t pt-4">
                    {job.applications_count !== undefined && (
                      <div className="flex items-center gap-1">
                        <span className="font-medium">{job.applications_count}</span>
                        <span>Applications</span>
                      </div>
                    )}
                    {job.posted_at && (
                      <div className="flex items-center gap-1">
                        {getStatusIcon(job.status)}
                        <span>{new Date(job.posted_at).toLocaleDateString()}</span>
                      </div>
                    )}
                  </div>

                  {job.facebook_post_url && (
                    <div className="space-y-2">
                      <a
                        href={job.facebook_post_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-blue-600 hover:underline text-sm flex items-center gap-1"
                      >
                        <Facebook className="h-3 w-3" />
                        View on Facebook →
                      </a>
                      <button
                        onClick={() => { setLogsJob(job); setIsLogsOpen(true); }}
                        className="text-blue-600 hover:underline text-sm flex items-center gap-1"
                      >
                        <Facebook className="h-3 w-3" />
                        View Post History & Metrics
                      </button>
                    </div>
                  )}

                  <div className="flex gap-2 border-t pt-4">
                    <Button
                      variant="outline"
                      size="sm"
                      className="flex-1 gap-2"
                      onClick={() => handleEditClick(job)}
                    >
                      <Edit className="h-4 w-4" />
                      Edit
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-2 text-red-600 hover:text-red-600 hover:bg-red-50"
                      onClick={() => { setActionJob(job); setActionType('delete'); }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>

                  {job.status !== 'open' && (
                    <Button
                      variant="secondary"
                      size="sm"
                      className="w-full"
                      onClick={() => { setActionJob(job); setActionType('publish'); }}
                    >
                      Publish
                    </Button>
                  )}
                  {job.status === 'open' && (
                    <Button
                      variant="secondary"
                      size="sm"
                      className="w-full"
                      onClick={() => { setActionJob(job); setActionType('close'); }}
                    >
                      Close Job
                    </Button>
                  )}
                </CardContent>
              </Card>
            ))
          ) : (
            <Card className="col-span-full">
              <CardContent className="pt-8 pb-8">
                <div className="text-center space-y-2">
                  <p className="text-muted-foreground">No job postings found</p>
                  <Button variant="outline" onClick={handleCreateClick}>
                    <Plus className="h-4 w-4 mr-2" />
                    Create your first job posting
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      {/* ── Action Confirmation Dialog ─────────────────────────────────────────── */}
      <Dialog open={!!actionType && !!actionJob} onOpenChange={handleCancelAction}>
        <DialogContent className="max-w-md">
          {config && actionJob && (
            <>
              <DialogHeader>
                <div className="flex items-center gap-4">
                  <div className={`flex items-center justify-center w-12 h-12 rounded-full flex-shrink-0 ${config.iconBg}`}>
                    {config.icon}
                  </div>
                  <div>
                    <DialogTitle className="text-lg">{config.title}</DialogTitle>
                    <p className="text-sm text-muted-foreground mt-0.5 font-medium">{actionJob.title}</p>
                  </div>
                </div>
              </DialogHeader>

              {/* ✅ No <div> inside DialogDescription — use a sibling div instead */}
              <div className="space-y-3 py-1">
                <p className="text-sm text-muted-foreground">{config.description}</p>
                {actionType === 'delete' && (
                  <div className="flex items-start gap-2 bg-red-50 border border-red-200 rounded-lg p-3">
                    <AlertTriangle className="h-4 w-4 text-red-600 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-red-700 font-medium">This action cannot be undone.</p>
                  </div>
                )}
              </div>

              <DialogFooter className="gap-2 sm:gap-2">
                <Button
                  variant="outline"
                  onClick={handleCancelAction}
                  disabled={isActionLoading}
                >
                  Cancel
                </Button>
                <Button
                  variant={config.confirmVariant}
                  onClick={handleConfirmAction}
                  disabled={isActionLoading}
                >
                  {isActionLoading ? 'Processing...' : config.confirmLabel}
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      {/* ── Create / Edit Modal ───────────────────────────────────────────────── */}
      <JobPostingCreateEditModal
        isOpen={isModalOpen}
        isEditing={!!editingJob}
        jobPosting={editingJob}
        departments={departments}
        onClose={handleModalClose}
        onSubmit={handleFormSubmit}
      />

      {/* ── Facebook Preview Modal ────────────────────────────────────────────── */}
      {previewJob && (
        <FacebookPreviewModal
          isOpen={isPreviewOpen}
          onClose={() => { setIsPreviewOpen(false); setPreviewJob(undefined); }}
          jobPosting={previewJob}
          onConfirm={handleConfirmFacebookPost}
        />
      )}

      {/* ── Facebook Logs Modal ───────────────────────────────────────────────── */}
      {logsJob && (
        <FacebookLogsModal
          isOpen={isLogsOpen}
          onClose={() => { setIsLogsOpen(false); setLogsJob(undefined); }}
          jobPosting={logsJob}
        />
      )}
    </AppLayout>
  );
}
import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { KanbanIcon, ListIcon, Download } from 'lucide-react';
import { PipelineKanban } from '@/components/ats/pipeline-kanban';
import { PipelineList } from '@/components/ats/pipeline-list';
import type { Application, ApplicationStatus } from '@/types/ats-pages';
import type { BreadcrumbItem } from '@/types';

interface PipelineColumn {
  status: ApplicationStatus;
  label: string;
  count: number;
  applications: Application[];
}

interface HiringPipelineIndexProps {
  pipeline: PipelineColumn[];
  summary: {
    total_candidates: number;
    active_applications: number;
    interviews_this_week: number;
    offers_pending: number;
    hires_this_month: number;
  };
  jobPostings: Array<{ id: number; title: string; open_positions: number }>;
  sources: Array<{ value: string; label: string }>;
  viewMode: 'kanban' | 'list';
  filters: { job_posting_id?: number; source?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Hiring Pipeline', href: '/hr/ats/hiring-pipeline' },
];

const summaryCards = [
  { title: 'Total Candidates', key: 'total_candidates' as const, color: 'bg-blue-50 border-blue-200', icon: '👥' },
  { title: 'Active Applications', key: 'active_applications' as const, color: 'bg-purple-50 border-purple-200', icon: '📋' },
  { title: 'Interviews This Week', key: 'interviews_this_week' as const, color: 'bg-orange-50 border-orange-200', icon: '📅' },
  { title: 'Offers Pending', key: 'offers_pending' as const, color: 'bg-green-50 border-green-200', icon: '📧' },
  { title: 'Hires This Month', key: 'hires_this_month' as const, color: 'bg-emerald-50 border-emerald-200', icon: '✅' },
];

const applicationStatuses: Array<{ value: ApplicationStatus; label: string }> = [
  { value: 'submitted', label: 'Submitted' },
  { value: 'shortlisted', label: 'Shortlisted' },
  { value: 'interviewed', label: 'Interviewed' },
  { value: 'offered', label: 'Offered' },
  { value: 'hired', label: 'Hired' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'withdrawn', label: 'Withdrawn' },
];

export default function HiringPipelineIndex({
  pipeline,
  summary,
  jobPostings,
  sources,
  viewMode: initialViewMode,
  filters,
}: HiringPipelineIndexProps) {
  const [viewMode, setViewMode] = useState<'kanban' | 'list'>(initialViewMode);
  const [selectedJob, setSelectedJob] = useState<number | 'all'>(filters.job_posting_id || 'all');
  const [selectedSource, setSelectedSource] = useState<string | 'all'>(filters.source || 'all');
  const [selectedStatus, setSelectedStatus] = useState<ApplicationStatus | 'all'>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Show toast on flash success
  const { props } = usePage();
  useEffect(() => {
    if (props.flash && (props.flash as Record<string, string>).success) {
      toast.success((props.flash as Record<string, string>).success);
    }
  }, [props.flash]);

  const handleViewApplication = (app: Application) => {
    window.location.href = `/hr/ats/applications/${app.id}`;
  };

  const handleChangeApplicationStatus = (app: Application, newStatus: ApplicationStatus, notes?: string) => {
    // ✅ Uses the correct hiring-pipeline route: PUT /hr/ats/hiring-pipeline/applications/{id}/move
    router.put(`/hr/ats/hiring-pipeline/applications/${app.id}/move`, {
      status: newStatus,
      notes: notes ?? '',
    }, {
      preserveScroll: true,
      onError: () => {
        toast.error('Failed to update application status. Please try again.');
      },
    });
  };

  const handleDeleteApplication = (app: Application) => {
    router.delete(`/hr/ats/applications/${app.id}`, {
      preserveScroll: true,
      onError: () => {
        toast.error('Failed to delete application. Please try again.');
      },
    });
  };

  useEffect(() => {
    localStorage.setItem('hiring-pipeline-view', viewMode);
  }, [viewMode]);

  const filteredPipeline = pipeline.map((column) => ({
    ...column,
    applications: column.applications.filter((app) => {
      const matchesJob = selectedJob === 'all' || app.job_id === selectedJob;
      const matchesSource = selectedSource === 'all' || (app.candidate?.source === selectedSource);
      const matchesStatus = selectedStatus === 'all' || app.status === selectedStatus;
      const matchesSearch =
        searchQuery === '' ||
        (app.candidate_name?.toLowerCase().includes(searchQuery.toLowerCase()) || false) ||
        (app.job_title?.toLowerCase().includes(searchQuery.toLowerCase()) || false);
      return matchesJob && matchesSource && matchesStatus && matchesSearch;
    }),
  }));

  const handleExportCSV = () => {
    const allApps = filteredPipeline.flatMap((col) => col.applications);
    const headers = ['Candidate Name', 'Job Title', 'Status', 'Applied Date', 'Email', 'Phone'];
    const rows = allApps.map((app) => [
      app.candidate_name || '',
      app.job_title || '',
      app.status.toUpperCase(),
      app.applied_at,
      app.candidate_email || '',
      app.candidate_phone || '',
    ]);

    const csv = [headers.join(','), ...rows.map((row) => row.map((cell) => `"${cell}"`).join(','))].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `hiring-pipeline-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Hiring Pipeline" />

      <div className="space-y-6 p-6">

        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Hiring Pipeline</h1>
            <p className="mt-2 text-sm text-muted-foreground">
              Manage applications and track candidates through the hiring process
            </p>
          </div>
          <div className="flex gap-2">
            <Button
              variant={viewMode === 'kanban' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setViewMode('kanban')}
              className="gap-2"
            >
              <KanbanIcon className="h-4 w-4" />
              Kanban
            </Button>
            <Button
              variant={viewMode === 'list' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setViewMode('list')}
              className="gap-2"
            >
              <ListIcon className="h-4 w-4" />
              List
            </Button>
            <Button variant="outline" size="sm" onClick={handleExportCSV} className="gap-2">
              <Download className="h-4 w-4" />
              Export CSV
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
          {summaryCards.map((card) => (
            <Card key={card.key} className={`border ${card.color}`}>
              <CardContent className="pt-6">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium text-muted-foreground">{card.title}</p>
                    <p className="mt-2 text-2xl font-bold">{summary[card.key]}</p>
                  </div>
                  <span className="text-2xl">{card.icon}</span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Filters</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
              <div>
                <label className="text-xs font-medium text-muted-foreground">Search</label>
                <input
                  type="text"
                  placeholder="Search by candidate or job..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
              </div>

              <div>
                <label className="text-xs font-medium text-muted-foreground">Job Posting</label>
                <select
                  value={selectedJob}
                  onChange={(e) => setSelectedJob(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="all">All Jobs</option>
                  {jobPostings.map((job) => (
                    <option key={job.id} value={job.id}>
                      {job.title}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs font-medium text-muted-foreground">Source</label>
                <select
                  value={selectedSource}
                  onChange={(e) => setSelectedSource(e.target.value)}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="all">All Sources</option>
                  {sources.map((source) => (
                    <option key={source.value} value={source.value}>
                      {source.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs font-medium text-muted-foreground">Status</label>
                <select
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus((e.target.value === 'all' ? 'all' : e.target.value) as ApplicationStatus | 'all')}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="all">All Statuses</option>
                  {applicationStatuses.map((status) => (
                    <option key={status.value} value={status.value}>
                      {status.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        {viewMode === 'kanban' ? (
          <PipelineKanban
            pipeline={filteredPipeline}
            onChangeApplicationStatus={handleChangeApplicationStatus}
          />
        ) : (
          <PipelineList
            pipeline={filteredPipeline}
            onViewApplication={handleViewApplication}
            onChangeApplicationStatus={handleChangeApplicationStatus}
            onDeleteApplication={handleDeleteApplication}
          />
        )}
      </div>
    </AppLayout>
  );
}
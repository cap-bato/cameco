// InterviewsIndex.tsx
import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Calendar, List, Plus } from 'lucide-react';
import { InterviewCalendar } from '@/components/ats/interview-calendar';
import { InterviewTable } from '@/components/ats/interview-table';
import { InterviewScheduleModal } from '@/components/ats/interview-schedule-modal';
import type { PageProps } from '@inertiajs/core';
import type { Interview, InterviewStatus } from '@/types/ats-pages';
import axios from 'axios';

interface InterviewsIndexProps extends PageProps {
  interviews: Interview[];
  statistics: {
    total_interviews: number;
    scheduled: number;
    completed: number;
    cancelled: number;
    no_show: number;
    upcoming_this_week: number;
  };
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'HR', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Interviews', href: '/hr/ats/interviews' },
];

export default function InterviewsIndex({ interviews, statistics }: InterviewsIndexProps) {
  const [viewMode, setViewMode] = useState<'calendar' | 'list'>('calendar');
  const [calendarView, setCalendarView] = useState<'month' | 'week' | 'day'>('month');

  const [isScheduleModalOpen, setIsScheduleModalOpen] = useState(false);
  const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
  const [selectedInterview, setSelectedInterview] = useState<Interview | null>(null);

  const [filterStatus, setFilterStatus] = useState<InterviewStatus | 'all'>('all');
  const [filterInterviewer, setFilterInterviewer] = useState<string>('all');

  const [selectedApplicationId, setSelectedApplicationId] = useState<number | null>(null);
  const [selectedCandidateId, setSelectedCandidateId] = useState<number | null>(null);
  const [selectedJobTitle, setSelectedJobTitle] = useState<string>('');
  const [selectedInterviewer, setSelectedInterviewer] = useState<string>('');

  // Feedback States
  const [showFeedbackModal, setShowFeedbackModal] = useState(false);
  const [feedback, setFeedback] = useState<string>('');

  const interviewers = Array.from(
    new Set(interviews.map((i) => i.interviewer_name).filter(Boolean))
  ) as string[];

  const getFilteredInterviews = () => {
    return interviews.filter((interview) => {
      if (filterStatus !== 'all' && interview.status !== filterStatus) return false;
      if (filterInterviewer !== 'all' && interview.interviewer_name !== filterInterviewer)
        return false;
      return true;
    });
  };

  const filteredInterviews = getFilteredInterviews();

  const handleScheduleInterview = async (data: {
    scheduled_date: string;
    scheduled_time: string;
    duration_minutes: number;
    location_type: string;
  }) => {
    try {
      const payload = {
        ...data,
        application_id: selectedApplicationId,
        candidate_id: selectedCandidateId,
        job_title: selectedJobTitle,
        interviewer_name: selectedInterviewer,
      };
      await axios.post('/hr/ats/interviews', payload);
      setIsScheduleModalOpen(false);
    } catch (error) {
      console.error('Failed to schedule interview:', error);
    }
  };

  const handleRescheduleInterview = (interview: Interview) => {
    setSelectedInterview(interview);
    setIsRescheduleModalOpen(true);
  };

  const handleSubmitReschedule = async (data: {
    scheduled_date: string;
    scheduled_time: string;
  }) => {
    if (!selectedInterview) return;
    try {
      await axios.put(`/hr/ats/interviews/${selectedInterview.id}`, data);
      alert('Interview rescheduled successfully');
      window.location.reload();
      setIsRescheduleModalOpen(false);
      setSelectedInterview(null);
    } catch (error) {
      console.error('Failed to reschedule interview:', error);
    }
  };

  const handleDeleteInterview = async (interview: Interview) => {
    if (!window.confirm('Cancel this interview?')) return;
    try {
      await axios.post(`/hr/ats/interviews/${interview.id}/cancel`, {
        cancellation_reason: 'Cancelled via UI',
      });
    } catch (error) {
      console.error('Failed to cancel:', error);
    }
  };



const handleAddFeedback = (interview: Interview) => {
    setSelectedInterview(interview);
    setFeedback(interview.feedback || '');
    setShowFeedbackModal(true);
  };

  // 2️⃣ Also define submitFeedback here
  const submitFeedback = async () => {
    if (!selectedInterview) return;

    try {
      await router.put(`/hr/ats/interviews/${selectedInterview.id}`, { feedback });
      alert("Feedback saved!");
      setShowFeedbackModal(false);
      setFeedback("");
      window.location.reload();
    } catch (error) {
      console.error("Failed to save feedback:", error);
      alert("Error saving feedback");
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Interviews" />
      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Interviews</h1>
            <p className="text-muted-foreground mt-2">
              Manage and schedule interviews for candidates
            </p>
          </div>
          <Button onClick={() => setIsScheduleModalOpen(true)} size="lg">
            <Plus className="mr-2 h-4 w-4" /> Schedule Interview
          </Button>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Scheduled
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{statistics.scheduled}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Completed
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-green-600">{statistics.completed}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Cancelled
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-red-600">{statistics.cancelled}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                No Show
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-orange-600">{statistics.no_show}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                This Week
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-purple-600">
                {statistics.upcoming_this_week}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <div className="space-y-4">
              <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-muted-foreground">View:</span>
                  <Button
                    variant={viewMode === 'calendar' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setViewMode('calendar')}
                  >
                    <Calendar className="mr-2 h-4 w-4" />
                    Calendar
                  </Button>

                  <Button
                    variant={viewMode === 'list' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setViewMode('list')}
                  >
                    <List className="mr-2 h-4 w-4" />
                    List
                  </Button>
                </div>

                {viewMode === 'calendar' && (
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-muted-foreground">Period:</span>
                    <Button
                      variant={calendarView === 'month' ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => setCalendarView('month')}
                    >
                      Month
                    </Button>
                    <Button
                      variant={calendarView === 'week' ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => setCalendarView('week')}
                    >
                      Week
                    </Button>
                    <Button
                      variant={calendarView === 'day' ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => setCalendarView('day')}
                    >
                      Day
                    </Button>
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-sm font-medium text-muted-foreground">Filter:</span>

                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                      Status: {filterStatus === 'all' ? 'All' : filterStatus}
                    </Button>
                  </DropdownMenuTrigger>

                  <DropdownMenuContent align="start">
                    <DropdownMenuItem onClick={() => setFilterStatus('all')}>
                      All
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterStatus('scheduled')}>
                      Scheduled
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterStatus('completed')}>
                      Completed
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterStatus('cancelled')}>
                      Cancelled
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterStatus('no_show')}>
                      No Show
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                      Interviewer: {filterInterviewer === 'all' ? 'All' : filterInterviewer}
                    </Button>
                  </DropdownMenuTrigger>

                  <DropdownMenuContent align="start">
                    <DropdownMenuItem onClick={() => setFilterInterviewer('all')}>
                      All
                    </DropdownMenuItem>

                    {interviewers.map((interviewer) => (
                      <DropdownMenuItem
                        key={interviewer}
                        onClick={() => setFilterInterviewer(interviewer)}
                      >
                        {interviewer}
                      </DropdownMenuItem>
                    ))}
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </CardHeader>
        </Card>

        {/* Calendar or List */}
        {viewMode === 'calendar' ? (
          <InterviewCalendar
            interviews={filteredInterviews}
            view={calendarView}
            onSelectDate={() => setIsScheduleModalOpen(true)}
            onReschedule={handleRescheduleInterview}
            onAddFeedback={handleAddFeedback}
            onCancel={handleDeleteInterview}
          />
        ) : (
          <InterviewTable
            interviews={filteredInterviews}
            onReschedule={handleRescheduleInterview}
            onAddFeedback={handleAddFeedback}
            onCancel={handleDeleteInterview}
          />
        )}
      </div>

      {/* Schedule Modal */}
      <InterviewScheduleModal
        isOpen={isScheduleModalOpen}
        onClose={() => setIsScheduleModalOpen(false)}
        onSubmit={handleScheduleInterview}
        candidateName="Select Candidate"
        applicationId={0}
      />

      {/* Reschedule Modal */}
      {selectedInterview && (
        <InterviewScheduleModal
          isOpen={isRescheduleModalOpen}
          onClose={() => setIsRescheduleModalOpen(false)}
          onSubmit={handleSubmitReschedule}
          candidateName={selectedInterview.candidate_name || ''}
          scheduledDate={selectedInterview.scheduled_date}
          scheduledTime={selectedInterview.scheduled_time}
        />
      )}

      {/* Feedback Modal */}
      {showFeedbackModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">
              Add Feedback – Interview #{selectedInterview?.id}
            </h2>

            <textarea
              className="w-full border rounded p-3 min-h-[120px]"
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              placeholder="Write feedback here..."
            />

            <div className="flex justify-end mt-4 gap-2">
              <Button variant="outline" onClick={() => setShowFeedbackModal(false)}>
                Cancel
              </Button>
              <Button onClick={submitFeedback}>Save</Button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}

import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Clock,
  MapPin,
  User,
  Calendar,
  MoreVertical,
  Edit,
  CheckCircle,
  XCircle,
  ArrowRight,
  Star,
  FileText,
} from 'lucide-react';
import type { PageProps } from '@inertiajs/core';
import type { Interview } from '@/types/ats-pages';
import { InterviewFeedbackModal } from '@/components/ats/interview-feedback-modal';
import { InterviewScheduleModal } from '@/components/ats/interview-schedule-modal';
import { InterviewStatusBadge } from '@/components/ats/interview-status-badge';

interface InterviewShowProps extends PageProps {
  interview: Interview;
  relatedApplication?: {
    id: number;
    candidate_name: string;
    job_title: string;
    status: string;
    applied_date: string;
  };
  timeline?: Array<{
    id: number;
    event: string;
    description: string;
    timestamp: string;
    user: string;
  }>;
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'HR', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Interviews', href: '/hr/ats/interviews' },
  { title: 'Interview Details', href: '#' },
];


export default function InterviewShow({
  interview,
  relatedApplication,
  timeline = [],
}: InterviewShowProps) {
  const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
  const [isFeedbackModalOpen, setIsFeedbackModalOpen] = useState(false);

  const handleReschedule = async (data: {
    scheduled_date: string;
    scheduled_time: string;
    duration_minutes: number;
    location_type: string;
  }) => {
    console.log('Rescheduling interview:', interview.id, data);
    await new Promise((resolve) => setTimeout(resolve, 600));
    setIsRescheduleModalOpen(false);
  };

  const handleSubmitFeedback = async (data: {
    overall_score: number;
    recommendation: 'hire' | 'pending' | 'reject';
    feedback: string;
    strengths?: string;
    weaknesses?: string;
    technical_skills?: number;
    communication_skills?: number;
    cultural_fit?: number;
    interviewer_notes?: string;
  }) => {
    console.log('Submitting feedback for interview:', interview.id, data);
    await new Promise((resolve) => setTimeout(resolve, 600));
    setIsFeedbackModalOpen(false);
  };

  const handleMarkCompleted = () => {
    console.log('Marking interview as completed:', interview.id);
    setIsFeedbackModalOpen(true);
  };

  const handleCancelInterview = () => {
    if (
      confirm('Are you sure you want to cancel this interview? This action cannot be undone.')
    ) {
      console.log('Cancelling interview:', interview.id);
    }
  };

  const isScheduled = interview.status === 'scheduled';
  const isCompleted = interview.status === 'completed';

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Interview - ${interview.candidate_name}`} />

      <div className="space-y-6 p-6">
        {/* Header Section */}
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1">
            <div className="flex items-center gap-3">
              <h1 className="text-3xl font-bold text-slate-900">
                {interview.candidate_name}
              </h1>
                  <InterviewStatusBadge status={interview.status} />
            </div>
            <p className="mt-1 text-slate-600">{interview.job_title}</p>
          </div>

          {/* Actions Dropdown */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="icon">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              {isScheduled && (
                <>
                  <DropdownMenuItem onClick={() => setIsRescheduleModalOpen(true)}>
                    <Edit className="mr-2 h-4 w-4" />
                    Reschedule Interview
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleMarkCompleted}>
                    <CheckCircle className="mr-2 h-4 w-4" />
                    Mark as Completed
                  </DropdownMenuItem>
                </>
              )}
              {isCompleted && (
                <DropdownMenuItem onClick={() => setIsFeedbackModalOpen(true)}>
                  <Edit className="mr-2 h-4 w-4" />
                  Edit Feedback
                </DropdownMenuItem>
              )}
              {(isScheduled || isCompleted) && (
                <DropdownMenuItem
                  onClick={handleCancelInterview}
                  className="text-red-600"
                >
                  <XCircle className="mr-2 h-4 w-4" />
                  Cancel Interview
                </DropdownMenuItem>
              )}
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <DropdownMenuItem
                      disabled
                      className="cursor-not-allowed opacity-50"
                    >
                    </DropdownMenuItem>
                  </TooltipTrigger>
                  <TooltipContent>
                    <p>Google Calendar integration coming in Phase 2</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* Main Details Section */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Left Column - Interview Details */}
          <div className="lg:col-span-2 space-y-6">
            {/* Schedule Details Card */}
            <Card>
              <CardHeader>
                <CardTitle>Schedule Details</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="flex gap-3">
                    <Calendar className="h-5 w-5 text-slate-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-slate-600">Date</p>
                      <p className="text-slate-900 font-semibold">
                        {interview.scheduled_date}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <Clock className="h-5 w-5 text-slate-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-slate-600">Time</p>
                      <p className="text-slate-900 font-semibold">
                        {interview.scheduled_time}
                      </p>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="flex gap-3">
                    <Clock className="h-5 w-5 text-slate-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-slate-600">
                        Duration
                      </p>
                      <p className="text-slate-900 font-semibold">
                        {interview.duration_minutes} minutes
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <MapPin className="h-5 w-5 text-slate-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-slate-600">
                        Location
                      </p>
                      <p className="text-slate-900 font-semibold capitalize">
                        {interview.location_type.replace(/_/g, ' ')}
                      </p>
                    </div>
                  </div>
                </div>

                {interview.meeting_link && (
                  <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p className="text-sm font-medium text-blue-900 mb-1">
                      Meeting Link
                    </p>
                    <a
                      href={interview.meeting_link}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-blue-600 hover:underline break-all text-sm"
                    >
                      {interview.meeting_link}
                    </a>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Interviewer Details Card */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <User className="h-5 w-5" />
                  Interviewer
                </CardTitle>
              </CardHeader>
              <CardContent>
                  <div className="flex gap-3">
                    <div className="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                      <span className="font-semibold text-slate-600">
                        {interview.interviewer_name?.charAt(0) || '?'}
                      </span>
                    </div>
                    <div>
                      <p className="font-semibold text-slate-900">
                        {interview.interviewer_name || 'N/A'}
                      </p>
                      <p className="text-sm text-slate-600">Senior Recruiter</p>
                    </div>
                  </div>
              </CardContent>
            </Card>

            {/* Interview Notes Card */}
            {interview.notes && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <FileText className="h-5 w-5" />
                    Notes
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-slate-700 whitespace-pre-wrap">
                    {interview.notes}
                  </p>
                </CardContent>
              </Card>
            )}

            {/* Feedback Card (if completed) */}
            {isCompleted && interview.score !== null && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                    Interview Feedback
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm font-medium text-slate-600">
                        Overall Score
                      </p>
                      <p className="text-2xl font-bold text-blue-600">
                        {interview.score}/10
                      </p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-slate-600">
                        Recommendation
                      </p>
                      <p className="text-lg font-semibold mt-1 capitalize">
                        {interview.recommendation}
                      </p>
                    </div>
                  </div>

                  {interview.feedback && (
                    <div>
                      <p className="text-sm font-medium text-slate-600 mb-2">
                        Feedback
                      </p>
                      <p className="text-slate-700 whitespace-pre-wrap">
                        {interview.feedback}
                      </p>
                    </div>
                  )}

                  <div className="grid grid-cols-3 gap-4">
                    {interview.technical_skills_score !== null &&
                      interview.technical_skills_score !== undefined && (
                        <div>
                          <p className="text-xs font-medium text-slate-600">
                            Technical
                          </p>
                          <p className="text-lg font-semibold text-blue-600">
                            {interview.technical_skills_score}/10
                          </p>
                        </div>
                      )}
                    {interview.communication_score !== null &&
                      interview.communication_score !== undefined && (
                        <div>
                          <p className="text-xs font-medium text-slate-600">
                            Communication
                          </p>
                          <p className="text-lg font-semibold text-blue-600">
                            {interview.communication_score}/10
                          </p>
                        </div>
                      )}
                    {interview.cultural_fit_score !== null &&
                      interview.cultural_fit_score !== undefined && (
                        <div>
                          <p className="text-xs font-medium text-slate-600">
                            Cultural Fit
                          </p>
                          <p className="text-lg font-semibold text-blue-600">
                            {interview.cultural_fit_score}/10
                          </p>
                        </div>
                      )}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Timeline Section */}
            {timeline.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>Interview History</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {timeline.map((event, index) => (
                      <div key={event.id} className="flex gap-4">
                        <div className="flex flex-col items-center">
                          <div className="h-3 w-3 rounded-full bg-blue-500"></div>
                          {index < timeline.length - 1 && (
                            <div className="h-8 w-0.5 bg-slate-200 mt-2"></div>
                          )}
                        </div>
                        <div className="pb-4">
                          <p className="font-semibold text-slate-900">
                            {event.event}
                          </p>
                          <p className="text-sm text-slate-600">
                            {event.description}
                          </p>
                          <p className="text-xs text-slate-500 mt-1">
                            {event.timestamp} by {event.user}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>

          {/* Right Column - Related Info */}
          <div className="space-y-6">
            {/* Related Application Card */}
            {relatedApplication && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">
                    Related Application
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div>
                    <p className="text-sm font-medium text-slate-600">
                      Candidate
                    </p>
                    <p className="font-semibold text-slate-900">
                      {relatedApplication.candidate_name}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-slate-600">
                      Position
                    </p>
                    <p className="font-semibold text-slate-900">
                      {relatedApplication.job_title}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-slate-600">
                      Application Status
                    </p>
                    <Badge className="mt-1" variant="outline">
                      {relatedApplication.status}
                    </Badge>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-slate-600">
                      Applied Date
                    </p>
                    <p className="text-sm text-slate-900">
                      {relatedApplication.applied_date}
                    </p>
                  </div>
                  <Button
                    variant="outline"
                    className="w-full mt-4"
                    size="sm"
                    asChild
                  >
                    <a href={`/hr/ats/applications/${relatedApplication.id}`}>
                      <ArrowRight className="mr-2 h-4 w-4" />
                      View Application
                    </a>
                  </Button>
                </CardContent>
              </Card>
            )}

            {/* Quick Stats Card */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Interview Info</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-slate-600">
                    Status
                  </span>
                  <InterviewStatusBadge status={interview.status} />
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-slate-600">
                    Interview ID
                  </span>
                  <span className="text-sm text-slate-600">#{interview.id}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-slate-600">
                    Created
                  </span>
                  <span className="text-sm text-slate-600">
                    {interview.created_at}
                  </span>
                </div>
                {isCompleted && interview.completed_at && (
                  <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-slate-600">
                      Completed
                    </span>
                    <span className="text-sm text-slate-600">
                      {interview.completed_at}
                    </span>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {/* Reschedule Modal */}
      <InterviewScheduleModal
        isOpen={isRescheduleModalOpen}
        onClose={() => setIsRescheduleModalOpen(false)}
        onSubmit={handleReschedule}
        candidateName={interview.candidate_name || 'Candidate'}
        applicationId={interview.application_id}
      />

      {/* Feedback Modal */}
      <InterviewFeedbackModal
        isOpen={isFeedbackModalOpen}
        onClose={() => setIsFeedbackModalOpen(false)}
        onSubmit={handleSubmitFeedback}
        interview={{
          id: interview.id,
          candidate_name: interview.candidate_name || 'Candidate',
          job_title: interview.job_title || 'Position',
          scheduled_date: interview.scheduled_date,
          scheduled_time: interview.scheduled_time,
          interviewer_name: interview.interviewer_name || 'Interviewer',
        }}
      />
    </AppLayout>
  );
}

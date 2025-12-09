import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import { ArrowLeft, Calendar, FileText } from 'lucide-react';
import { ApplicationStatusBadge } from '@/components/ats/application-status-badge';
import { ApplicationStatusModal } from '@/components/ats/application-status-modal';
import { RejectApplicationModal } from '@/components/ats/reject-application-modal';
import { OfferGenerationModal } from '@/components/ats/offer-generation-modal';
import { InterviewScheduleModal } from '@/components/ats/interview-schedule-modal';
import { AddNoteModal } from '@/components/ats/add-note-modal-v2';
import { ApplicationDetailsTab } from '@/components/ats/application-details-tab';
import { ApplicationInterviewsTab } from '@/components/ats/application-interviews-tab';
import { ApplicationTimelineTab } from '@/components/ats/application-timeline-tab';
import { ApplicationNotesTab } from '@/components/ats/application-notes-tab';
import type { PageProps } from '@inertiajs/core';
import type { Application, Interview, ApplicationStatusHistory, CandidateNote, ScheduleInterviewData } from '@/types/ats-pages';
import { formatDate } from '@/lib/date-utils';
import axios from 'axios';
import { router } from '@inertiajs/react';

interface ApplicationShowProps extends PageProps {
  application: Application & { candidate_email?: string; candidate_phone?: string };
  interviews: Interview[];
  status_history: ApplicationStatusHistory[];
  notes: CandidateNote[];
  can_schedule_interview: boolean;
  can_generate_offer: boolean;
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Applications', href: '/hr/ats/applications' },
  { title: 'View Application', href: '#' },
];

export default function ApplicationShow({
  application,
  interviews,
  status_history,
  notes,
  can_schedule_interview,
  can_generate_offer,
}: ApplicationShowProps) {
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
  const [isRejectModalOpen, setIsRejectModalOpen] = useState(false);
  const [isInterviewModalOpen, setIsInterviewModalOpen] = useState(false);
  const [isAddNoteModalOpen, setIsAddNoteModalOpen] = useState(false);
  const [isOfferModalOpen, setIsOfferModalOpen] = useState(false);

  /** Status update */
const handleUpdateStatus = async (data: { status: string; notes?: string }) => {
  try {
    await axios.post(`/hr/ats/applications/${application.id}/update-status`, data);
    router.reload(); // refresh page to reflect status
  } catch (err: any) {
    console.error('Failed to update status:', err.response?.data || err.message);
  }
};

/** Reject application */
const handleRejectApplication = async (data: { reason: string }) => {
  try {
    await axios.post(`/hr/ats/applications/${application.id}/reject`, data);
    router.visit('/hr/ats/applications'); // redirect back to listing
  } catch (err: any) {
    console.error('Failed to reject application:', err.response?.data || err.message);
  }
};

/** Schedule interview */
const handleScheduleInterview = async (data: ScheduleInterviewData) => {
  try {
    await axios.post(`/hr/ats/applications/${application.id}/schedule-interview`, data);
    router.reload(); // refresh interviews tab
  } catch (err: any) {
    console.error('Failed to schedule interview:', err.response?.data || err.message);
  }
};

/** Add note */
const handleAddNote = async (data: { note: string; is_private: boolean }) => {
  try {
    await axios.post(`/hr/ats/applications/${application.id}/notes`, data);
    router.reload(); // refresh notes tab
  } catch (err: any) {
    console.error('Failed to add note:', err.response?.data || err.message);
  }
};

/** Generate offer */
const handleOfferSubmit = async (data: { template: string; customMessage?: string }) => {
  try {
    await axios.post(`/hr/ats/applications/${application.id}/generate-offer`, data);
    router.reload(); // refresh page to reflect "offered" status
  } catch (err: any) {
    console.error('Failed to generate offer:', err.response?.data || err.message);
  } finally {
    setIsOfferModalOpen(false);
  }
};

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Application - ${application.candidate_name ?? 'Candidate'}`} />

      <div className="space-y-6 p-6">
        {/* Back Button */}
        <Link href="/hr/ats/applications" className="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
          <ArrowLeft className="h-4 w-4" />
          Back to Applications
        </Link>

        {/* Application Header */}
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-start justify-between gap-6">
              <div className="space-y-4">
                <div>
                  <h1 className="text-3xl font-bold">{application.candidate_name ?? 'Unknown Candidate'}</h1>
                  <p className="text-muted-foreground mt-2">{application.job_title}</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Email: {application.candidate_email ?? 'N/A'}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Phone: {application.candidate_phone ?? 'N/A'}
                  </p>
                </div>

                <div className="flex items-center gap-4">
                  <div>
                    <p className="text-sm text-muted-foreground">Status</p>
                    <ApplicationStatusBadge status={application.status} />
                  </div>
                  {application.score && (
                    <div>
                      <p className="text-sm text-muted-foreground">Score</p>
                      <p className="text-lg font-semibold">{application.score}%</p>
                    </div>
                  )}
                  <div>
                    <p className="text-sm text-muted-foreground">Applied</p>
                    <p className="text-sm font-medium">{formatDate(application.applied_at)}</p>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex flex-col gap-2">
                <Button onClick={() => setIsStatusModalOpen(true)}>Update Status</Button>

                {can_schedule_interview && (
                  <Button onClick={() => setIsInterviewModalOpen(true)} variant="outline">
                    <Calendar className="mr-2 h-4 w-4" /> Schedule Interview
                  </Button>
                )}

                {can_generate_offer && (
                  <Button onClick={() => setIsOfferModalOpen(true)} variant="outline">
                    <FileText className="mr-2 h-4 w-4" /> Generate Offer
                  </Button>
                )}

                {application.status !== 'rejected' && application.status !== 'withdrawn' && (
                  <Button
                    onClick={() => setIsRejectModalOpen(true)}
                    variant="outline"
                    className="text-red-600 hover:text-red-600"
                  >
                    Reject
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tabs */}
        <Tabs defaultValue="details" className="space-y-4">
          <TabsList>
            <TabsTrigger value="details">Details</TabsTrigger>
            <TabsTrigger value="interviews">
              Interviews {interviews.length > 0 && `(${interviews.length})`}
            </TabsTrigger>
            <TabsTrigger value="timeline">Timeline</TabsTrigger>
            <TabsTrigger value="notes">Notes {notes.length > 0 && `(${notes.length})`}</TabsTrigger>
          </TabsList>

          <TabsContent value="details" className="space-y-4">
            <ApplicationDetailsTab application={application} />
          </TabsContent>

          <TabsContent value="interviews" className="space-y-4">
            <ApplicationInterviewsTab interviews={interviews} />
          </TabsContent>

          <TabsContent value="timeline" className="space-y-4">
            <ApplicationTimelineTab statusHistory={status_history} />
          </TabsContent>

          <TabsContent value="notes" className="space-y-4">
            <ApplicationNotesTab
              notes={notes}
              onAddNoteClick={() => setIsAddNoteModalOpen(true)}
            />
          </TabsContent>
        </Tabs>
      </div>

      {/* Modals */}
      <ApplicationStatusModal
        isOpen={isStatusModalOpen}
        onClose={() => setIsStatusModalOpen(false)}
        onSubmit={handleUpdateStatus}
        currentStatus={application.status}
        candidateName={application.candidate_name ?? 'Candidate'}
      />

      <RejectApplicationModal
        isOpen={isRejectModalOpen}
        onClose={() => setIsRejectModalOpen(false)}
        onSubmit={handleRejectApplication}
        candidateName={application.candidate_name ?? 'Candidate'}
      />

      <InterviewScheduleModal
        isOpen={isInterviewModalOpen}
        onClose={() => setIsInterviewModalOpen(false)}
        onSubmit={handleScheduleInterview}
        candidateName={application.candidate_name ?? 'Candidate'}
        applicationId={application.id}
      />

      <AddNoteModal
        isOpen={isAddNoteModalOpen}
        onClose={() => setIsAddNoteModalOpen(false)}
        onSubmit={handleAddNote}
        itemName={application.candidate_name ?? 'Candidate'}
        context="for application #"
      />

      <OfferGenerationModal
        isOpen={isOfferModalOpen}
        onClose={() => setIsOfferModalOpen(false)}
        onSubmit={handleOfferSubmit}
        candidateName={application.candidate_name ?? 'Candidate'}
      />
    </AppLayout>
  );
}

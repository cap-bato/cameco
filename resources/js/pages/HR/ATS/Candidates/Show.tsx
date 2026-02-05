import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MessageSquare, Download, Edit, Phone, Mail, Calendar } from 'lucide-react';
import { CandidateSourceBadge } from '@/components/ats/candidate-source-badge';
import { CandidateStatusBadge } from '@/components/ats/candidate-status-badge';
import { CandidateTimeline } from '@/components/ats/candidate-timeline';
import { AddCandidateModal, type CandidateFormData } from '@/components/ats/add-candidate-modal';
import { AddNoteModal } from '@/components/ats/add-note-modal';
import type { PageProps } from '@inertiajs/core';
import type { Candidate, Application, Interview, CandidateNote } from '@/types/ats-pages';
import axios from 'axios';
interface CandidateShowProps extends PageProps {
  candidate: Candidate;
  applications: Application[];
  interviews: Interview[];
  notes: CandidateNote[];
}

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'HR', href: '/hr/dashboard' },
  { title: 'Recruitment', href: '#' },
  { title: 'Candidates', href: '/hr/ats/candidates' },
  { title: 'View Candidate', href: '#' },
];

/**
 * Candidate Detail/Show Page
 * Displays full candidate information with applications, interviews, and notes
 */
export default function CandidateShow({
  candidate,
  applications,
  interviews,
  notes,
}: CandidateShowProps) {
  const [isNoteModalOpen, setIsNoteModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);

const handleAddNote = async (noteData: { note: string; is_private: boolean }) => {
  try {
    await axios.post(`/hr/ats/candidates/${candidate.id}/notes`, noteData);

    alert("Note added successfully!");
    window.location.reload(); 
  } catch (error: any) {
    console.error(error);
    alert(error.response?.data?.message || "Something went wrong.");
  }
};

const handleEditCandidate = async (candidateData: CandidateFormData) => {
  try {
    const formData = new FormData();

    // Append all fields
    Object.entries(candidateData).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        formData.append(key, value as any);
      }
    });

    // If a resume file exists
    if (candidateData.resume instanceof File) {
      formData.append('resume', candidateData.resume);
    }

    // Add method override for PUT
    formData.append("_method", "PUT");

    await axios.post(
      `/hr/ats/candidates/${candidate.id}`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" },
      }
    );

    alert("Candidate updated successfully!");
    window.location.reload();
  } catch (error: any) {
    console.error(error);
    alert(error.response?.data?.message || "Something went wrong.");
  } finally {
    setIsEditModalOpen(false);
  }
};


  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`${candidate.first_name} ${candidate.last_name}`} />

      <div className="space-y-6 p-6">
        {/* Candidate Header */}
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-start justify-between gap-6">
              <div className="flex-1">
                <div className="flex items-center gap-4 mb-4">
                  <div>
                    <h1 className="text-3xl font-bold">
                      {candidate.first_name} {candidate.last_name}
                    </h1>
                    <p className="text-muted-foreground mt-1">Applicant</p>
                  </div>
                </div>

                <div className="flex gap-2 mt-2">
                  <CandidateSourceBadge source={candidate.source} />
                  {candidate.status && (
                    <CandidateStatusBadge status={candidate.status} />
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  {candidate.email && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Mail className="h-4 w-4" />
                      <a href={`mailto:${candidate.email}`} className="hover:text-foreground">
                        {candidate.email}
                      </a>
                    </div>
                  )}
                  {candidate.phone && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Phone className="h-4 w-4" />
                      <a href={`tel:${candidate.phone}`} className="hover:text-foreground">
                        {candidate.phone}
                      </a>
                    </div>
                  )}
                  {candidate.applied_at && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Calendar className="h-4 w-4" />
                      Applied {new Date(candidate.applied_at).toLocaleDateString()}
                    </div>
                  )}
                </div>
              </div>

              <div className="flex gap-2">
                <Button variant="outline" className="gap-2" onClick={() => setIsEditModalOpen(true)}>
                  <Edit className="h-4 w-4" />
                  Edit
                </Button>
                <Button variant="outline" className="gap-2" onClick={() => setIsNoteModalOpen(true)}>
                  <MessageSquare className="h-4 w-4" />
                  Add Note
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tabs */}
        <Tabs defaultValue="profile" className="space-y-4">
          <TabsList>
            <TabsTrigger value="profile">Profile</TabsTrigger>
            <TabsTrigger value="applications">
              Applications {applications.length > 0 && `(${applications.length})`}
            </TabsTrigger>
            <TabsTrigger value="interviews">
              Interviews {interviews.length > 0 && `(${interviews.length})`}
            </TabsTrigger>
            <TabsTrigger value="notes">
              Notes {notes.length > 0 && `(${notes.length})`}
            </TabsTrigger>
            <TabsTrigger value="documents">Documents</TabsTrigger>
          </TabsList>

          {/* Profile Tab */}
          <TabsContent value="profile" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Personal Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">First Name</label>
                    <p className="mt-1">{candidate.first_name}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Last Name</label>
                    <p className="mt-1">{candidate.last_name}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Email</label>
                    <p className="mt-1">{candidate.email}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Phone</label>
                    <p className="mt-1">{candidate.phone || '-'}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Source</label>
                    <div className="mt-2">
                      <CandidateSourceBadge source={candidate.source} />
                    </div>
                  </div>
                  {candidate.resume_path && (
                    <div>
                      <label className="text-sm font-medium text-muted-foreground">Resume</label>
                      <div className="mt-2">
                        <Button variant="outline" size="sm" className="gap-2">
                          <Download className="h-4 w-4" />
                          {candidate.resume_path.split('/').pop() || 'Download Resume'}
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Applications Tab */}
          <TabsContent value="applications" className="space-y-4">
            {applications.length > 0 ? (
              <div className="space-y-4">
                {applications.map((app) => (
                  <Card key={app.id}>
                    <CardContent className="pt-6">
                      <div className="flex items-start justify-between gap-4">
                        <div className="flex-1">
                          <h3 className="font-semibold text-lg">{app.job_title}</h3>
                          <p className="text-sm text-muted-foreground mt-1">
                            Applied {new Date(app.applied_at).toLocaleDateString()}
                          </p>
                          <div className="mt-2">
                            <CandidateStatusBadge status={app.status} />
                          </div>
                        </div>
                        <Link href={`/hr/ats/applications/${app.id}`}>
                          <Button variant="outline">View</Button>
                        </Link>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <Card>
                <CardContent className="pt-6 text-center text-muted-foreground">
                  No applications yet
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Interviews Tab */}
          <TabsContent value="interviews" className="space-y-4">
            {interviews.length > 0 ? (
              <div className="space-y-4">
                {interviews.map((interview) => (
                  <Card key={interview.id}>
                    <CardContent className="pt-6">
                      <div className="flex items-start justify-between gap-4">
                        <div className="flex-1">
                          <h3 className="font-semibold text-lg">
                            {interview.location_type && interview.location_type.charAt(0).toUpperCase() + interview.location_type.slice(1).replace('_', ' ')} Interview
                          </h3>
                          <p className="text-sm text-muted-foreground mt-1">
                            Scheduled for{' '}
                            {new Date(interview.scheduled_date).toLocaleDateString()} at{' '}
                            {interview.scheduled_time}
                          </p>
                          <div className="mt-2">
                            <CandidateStatusBadge status={interview.status} />
                          </div>
                        </div>
                        <Button variant="outline">View Feedback</Button>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <Card>
                <CardContent className="pt-6 text-center text-muted-foreground">
                  No interviews scheduled
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Notes Tab */}
          <TabsContent value="notes" className="space-y-4">
            <div className="mb-4">
              <Button onClick={() => setIsNoteModalOpen(true)} className="gap-2">
                <MessageSquare className="h-4 w-4" />
                Add Note
              </Button>
            </div>
            <CandidateTimeline notes={notes} />
          </TabsContent>

          {/* Documents Tab */}
          <TabsContent value="documents" className="space-y-4">
            {candidate.resume_path ? (
              <Card>
                <CardContent className="pt-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-semibold">Resume</h3>
                      <p className="text-sm text-muted-foreground mt-1">
                        {candidate.resume_path.split('/').pop() || 'resume.pdf'}
                      </p>
                    </div>
                    <Button variant="outline" className="gap-2">
                      <Download className="h-4 w-4" />
                      Download
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ) : (
              <Card>
                <CardContent className="pt-6 text-center text-muted-foreground">
                  No documents uploaded
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>
      </div>
      {/* Add Note Modal */}
      <AddNoteModal
        isOpen={isNoteModalOpen}
        candidateName={`${candidate.first_name} ${candidate.last_name}`}
        onClose={() => setIsNoteModalOpen(false)}
        onSubmit={handleAddNote}
      />

      {/* Edit Candidate Modal */}
      <AddCandidateModal
        isOpen={isEditModalOpen}
        isEditing={true}
        initialData={{
          first_name: candidate.first_name,
          last_name: candidate.last_name,
          email: candidate.email,
          phone: candidate.phone,
          source: candidate.source,
        }}
        onClose={() => setIsEditModalOpen(false)}
        onSubmit={handleEditCandidate}
      />
    </AppLayout>
  );
}

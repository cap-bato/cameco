import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InterviewStatusBadge } from './interview-status-badge';
import { InterviewActionsMenu } from './interview-actions-menu';
import { InterviewScheduleModal } from './interview-schedule-modal';
import { X, Plus } from 'lucide-react';
import { hasAvailableSlots, getAvailableTimeSlots } from '@/utils/office-hours';
import type { Interview } from '@/types/ats-pages';
import axios from 'axios';
interface CalendarMonthViewProps {
  interviews: Interview[];
  currentDate: Date;
  onDateChange: (date: Date) => void;
  onSelectDate: () => void;
  onReschedule: (interview: Interview) => void;
  onAddFeedback: (interview: Interview) => void;
  onCancel: (interview: Interview) => void;
}

/**
 * Calendar Month View Component
 * Displays a 7-column grid (Sun-Sat) with interview dots/count on each day
 * Fully reusable for any calendar-based interface
 */
export function CalendarMonthView({
  interviews,
  currentDate,
  onDateChange,
  onSelectDate,
  onReschedule,
  onAddFeedback,
  onCancel,
}: CalendarMonthViewProps) {
  const [showAllInterviewsModal, setShowAllInterviewsModal] = useState(false);
  const [selectedDateForModal, setSelectedDateForModal] = useState<Date | null>(null);
  const [showScheduleModal, setShowScheduleModal] = useState(false);
  const [selectedDateForScheduling, setSelectedDateForScheduling] = useState<Date | null>(null);

    const [selectedApplicationId, setSelectedApplicationId] = useState<number | null>(null);
    const [selectedCandidateId, setSelectedCandidateId] = useState<number | null>(null);
    const [selectedJobTitle, setSelectedJobTitle] = useState<string>('');
    const [selectedInterviewer, setSelectedInterviewer] = useState<string>('');

  const openAllInterviewsModal = (date: Date) => {
    setSelectedDateForModal(date);
    setShowAllInterviewsModal(true);
  };

  const closeAllInterviewsModal = () => {
    setShowAllInterviewsModal(false);
    setSelectedDateForModal(null);
  };

  const openScheduleModal = (date: Date) => {
    setSelectedDateForScheduling(date);
    setShowScheduleModal(true);
  };

  const closeScheduleModal = () => {
    setShowScheduleModal(false);
    setSelectedDateForScheduling(null);
  };

  const currentYear = currentDate.getFullYear();
  const currentMonth = currentDate.getMonth();
  
  // Get first day of month and number of days
  const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
  const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
  const startDate = new Date(firstDay);
  startDate.setDate(startDate.getDate() - firstDay.getDay());
  
  const endDate = new Date(lastDay);
  endDate.setDate(endDate.getDate() + (6 - lastDay.getDay()));

  // Generate array of dates to display
  const getDaysArray = () => {
    const days = [];
    const current = new Date(startDate);
    while (current <= endDate) {
      days.push(new Date(current));
      current.setDate(current.getDate() + 1);
    }
    return days;
  };

  const days = getDaysArray();

  // Get interviews for a specific date
  const getInterviewsForDate = (date: Date) => {
    return interviews.filter((interview) => {
      const interviewDate = new Date(interview.scheduled_date);
      return (
        interviewDate.getFullYear() === date.getFullYear() &&
        interviewDate.getMonth() === date.getMonth() &&
        interviewDate.getDate() === date.getDate()
      );
    });
  };

  // Check if date is today
  const isToday = (date: Date) => {
    const today = new Date();
    return (
      date.getFullYear() === today.getFullYear() &&
      date.getMonth() === today.getMonth() &&
      date.getDate() === today.getDate()
    );
  };

  // Check if date is in current month
  const isCurrentMonth = (date: Date) => {
    return date.getMonth() === currentDate.getMonth();
  };

  // Check if date is in the past
  const isPastDate = (date: Date) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
  };

  // Day names
  const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  // Generate month and year options
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  // Generate year options (current year - 5 to current year + 5)
  const yearOptions = Array.from({ length: 11 }, (_, i) => currentYear - 5 + i);

  const handleMonthChange = (monthIndex: number) => {
    const newDate = new Date(currentDate);
    newDate.setMonth(monthIndex);
    // Ensure we have a valid date in the new month
    onDateChange(newDate);
  };

  const handleYearChange = (year: number) => {
    const newDate = new Date(currentDate);
    newDate.setFullYear(year);
    // Ensure we have a valid date in the new year
    onDateChange(newDate);
  };

  // Get available years and months from interview data
  const getAvailableYearsAndMonths = () => {
    const yearsMap = new Map<number, Set<number>>();
    
    interviews.forEach((interview) => {
      const date = new Date(interview.scheduled_date);
      const year = date.getFullYear();
      const month = date.getMonth();
      
      if (!yearsMap.has(year)) {
        yearsMap.set(year, new Set());
      }
      yearsMap.get(year)!.add(month);
    });
    
    return yearsMap;
  };

  const yearsAndMonthsMap = getAvailableYearsAndMonths();
  
  // Available years with data
  // const availableYears = Array.from(yearsAndMonthsMap.keys()).sort() as number[]; for future use
  const availableMonthsInYear = Array.from(yearsAndMonthsMap.get(currentYear) || new Set()).sort() as number[];

  return (
    <div className="space-y-4">
      {/* Date Picker Controls */}
      <div className="flex items-center justify-between gap-2 rounded-lg border p-4 bg-muted/50">
        <div className="flex items-center gap-2">
          <span className="text-sm font-semibold">Select Month:</span>
          <select
            value={currentMonth}
            onChange={(e) => handleMonthChange(parseInt(e.target.value))}
            className="px-3 py-2 rounded-md border bg-background text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ring"
          >
            {months.map((month, idx) => {
              const hasData = availableMonthsInYear.includes(idx);
              return (
                <option key={month} value={idx} disabled={!hasData}>
                  {month} {!hasData ? '(no data)' : ''}
                </option>
              );
            })}
          </select>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-sm font-semibold">Year:</span>
          <select
            value={currentYear}
            onChange={(e) => handleYearChange(parseInt(e.target.value))}
            className="px-3 py-2 rounded-md border bg-background text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ring"
          >
            {yearOptions.map((year) => {
              const hasData = yearsAndMonthsMap.has(year);
              return (
                <option key={year} value={year} disabled={!hasData}>
                  {year} {!hasData ? '(no data)' : ''}
                </option>
              );
            })}
          </select>
        </div>
      </div>

      {/* Day Headers */}
      <div className="grid grid-cols-7 gap-1">
        {dayNames.map((day) => (
          <div
            key={day}
            className="flex h-10 items-center justify-center font-semibold text-muted-foreground"
          >
            {day}
          </div>
        ))}
      </div>

      {/* Calendar Days */}
      <div className="grid grid-cols-7 gap-1 relative">
        {days.map((date, idx) => {
          const dayInterviews = getInterviewsForDate(date);
          const isCurrentMonthDay = isCurrentMonth(date);
          const isTodayDate = isToday(date);

          return (
            <div
              key={idx}
              className={`min-h-32 rounded-lg border p-2 relative overflow-visible ${
                isTodayDate
                  ? 'border-blue-600 bg-blue-100'
                  : isCurrentMonthDay
                    ? 'bg-neutral-300'
                    : 'bg-neutral-500'
              }`}
            >
              {/* Date Number */}
              <div
                className={`text-sm font-semibold ${
                  isCurrentMonthDay
                    ? 'text-foreground'
                    : 'text-muted-foreground'
                }`}
              >
                {date.getDate()}
              </div>

              {/* Interview Count */}
              {dayInterviews.length > 0 && (
                <div className="mt-1 space-y-1">
                  {dayInterviews.slice(0, 2).map((interview) => {
                    // Get status color
                    const getStatusColor = (status: string) => {
                      switch (status) {
                        case 'scheduled':
                          return { bg: 'bg-blue-100 hover:bg-blue-200', text: 'text-blue-900', subtext: 'text-blue-800' };
                        case 'completed':
                          return { bg: 'bg-green-100 hover:bg-green-200', text: 'text-green-900', subtext: 'text-green-800' };
                        case 'cancelled':
                          return { bg: 'bg-red-100 hover:bg-red-200', text: 'text-red-900', subtext: 'text-red-800' };
                        case 'no_show':
                          return { bg: 'bg-orange-100 hover:bg-orange-200', text: 'text-orange-900', subtext: 'text-orange-800' };
                        default:
                          return { bg: 'bg-blue-100 hover:bg-blue-200', text: 'text-blue-900', subtext: 'text-blue-800' };
                      }
                    };
                    const colors = getStatusColor(interview.status);

                    return (
                    <div
                      key={interview.id}
                      className="relative w-full"
                    >
                      {/* Clickable card with menu button in top-right corner */}
                      <div className="flex items-center justify-between gap-1">
                        <div
                          onClick={() => {
                            if (interview.id) {
                              router.visit(`/hr/ats/interviews/${interview.id}`);
                            }
                          }}
                          className={`flex-1 block rounded cursor-pointer ${colors.bg} p-1 text-xs transition-colors ${!interview.id ? 'pointer-events-none opacity-50' : ''}`}
                        >
                          <div className={`truncate font-medium ${colors.text}`}>
                            {interview.candidate_name}
                          </div>
                          <div className={`truncate ${colors.subtext}`}>
                            {interview.scheduled_time}
                          </div>
                        </div>
                        
                        {/* Menu button - positioned top-right */}
                              <div className="absolute top-0 right-0 flex items-start">
                          <InterviewActionsMenu
                            interview={interview}
                            onReschedule={onReschedule}
                            onAddFeedback={onAddFeedback}
                            onCancel={onCancel}
                            size="sm"
                          />
                        </div>
                      </div>
                    </div>
                    );
                  })}

                  {dayInterviews.length > 2 && (
                    <button
                      onClick={() => openAllInterviewsModal(date)}
                      className="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium cursor-pointer"
                    >
                      +{dayInterviews.length - 2} more
                    </button>
                  )}

                  {/* Add Interview Button - Show if available slots */}
                  {isCurrentMonthDay && !isPastDate(date) && hasAvailableSlots(date, interviews) && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="mt-1 h-6 text-xs text-muted-foreground hover:text-foreground w-full"
                      onClick={() => openScheduleModal(date)}
                    >
                      <Plus className="h-3 w-3 mr-1" />
                      Add
                    </Button>
                  )}
                </div>
              )}

              {/* Add Interview Button */}
              {isCurrentMonthDay && dayInterviews.length === 0 && !isPastDate(date) && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="mt-1 h-6 text-xs text-muted-foreground hover:text-foreground"
                  onClick={onSelectDate}
                >
                  + Add
                </Button>
              )}
            </div>
          );
        })}
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-4 border-t pt-4 text-xs">
        <div className="flex items-center gap-2">
          <InterviewStatusBadge status="scheduled" />
        </div>
        <div className="flex items-center gap-2">
          <InterviewStatusBadge status="completed" />
        </div>
        <div className="flex items-center gap-2">
          <InterviewStatusBadge status="no_show" />
        </div>
        <div className="flex items-center gap-2">
          <InterviewStatusBadge status="cancelled" />
        </div>
      </div>

      {/* All Interviews Modal */}
      {showAllInterviewsModal && selectedDateForModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
          <div className="bg-white rounded-lg shadow-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b p-4 sticky top-0 bg-white">
              <h2 className="text-lg font-semibold">
                All Interviews for {selectedDateForModal.toLocaleDateString('en-US', { 
                  month: 'long', 
                  day: 'numeric', 
                  year: 'numeric' 
                })}
              </h2>
              <button
                onClick={closeAllInterviewsModal}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-4 space-y-3">
              {getInterviewsForDate(selectedDateForModal).map((interview) => {
                const getStatusColor = (status: string) => {
                  switch (status) {
                    case 'scheduled':
                      return { bg: 'bg-blue-100 hover:bg-blue-200', text: 'text-blue-900', subtext: 'text-blue-800' };
                    case 'completed':
                      return { bg: 'bg-green-100 hover:bg-green-200', text: 'text-green-900', subtext: 'text-green-800' };
                    case 'cancelled':
                      return { bg: 'bg-red-100 hover:bg-red-200', text: 'text-red-900', subtext: 'text-red-800' };
                    case 'no_show':
                      return { bg: 'bg-orange-100 hover:bg-orange-200', text: 'text-orange-900', subtext: 'text-orange-800' };
                    default:
                      return { bg: 'bg-blue-100 hover:bg-blue-200', text: 'text-blue-900', subtext: 'text-blue-800' };
                  }
                };
                const colors = getStatusColor(interview.status);

                return (
                  <div
                    key={interview.id}
                    className="relative"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <div
                        onClick={() => {
                          if (interview.id) {
                            closeAllInterviewsModal();
                            router.visit(`/hr/ats/interviews/${interview.id}`);
                          }
                        }}
                        className={`flex-1 block rounded cursor-pointer ${colors.bg} p-2 transition-colors`}
                      >
                        <div className={`font-medium text-sm ${colors.text}`}>
                          {interview.candidate_name}
                        </div>
                        <div className={`text-xs ${colors.subtext}`}>
                          {interview.job_title} • {interview.scheduled_time}
                        </div>
                      </div>
                      
                      {/* Menu button */}
                      <div className="flex-shrink-0">
                        <InterviewActionsMenu
                          interview={interview}
                          onReschedule={onReschedule}
                          onAddFeedback={onAddFeedback}
                          onCancel={onCancel}
                          size="sm"
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Modal Footer */}
            <div className="border-t p-4 flex justify-between gap-2 sticky bottom-0 bg-white">
              {selectedDateForModal && hasAvailableSlots(selectedDateForModal, interviews) && (
                <Button
                  onClick={() => {
                    closeAllInterviewsModal();
                    openScheduleModal(selectedDateForModal);
                  }}
                  className="gap-2"
                >
                  <Plus className="h-4 w-4" />
                  Add Interview
                </Button>
              )}
              <div className="ml-auto">
                <Button
                  variant="outline"
                  onClick={closeAllInterviewsModal}
                >
                  Close
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

{selectedDateForScheduling && (
  <InterviewScheduleModal
    isOpen={showScheduleModal}
    onClose={closeScheduleModal}
    onSubmit={async (data) => {
      try {
        // Prepare payload matching the controller validation
        const payload = {
          scheduled_date: data.scheduled_date,
          scheduled_time: data.scheduled_time,
          duration_minutes: data.duration_minutes ?? 30,
          location_type: data.location_type,
          interviewer_name: selectedInterviewer,   // required
        };

        // Send request to backend
        const response = await axios.post('/hr/ats/interviews', payload);

        console.log('Interview scheduled:', response.data.interview);

        // Optionally update local state or refetch interviews here

        // Close the modal
        closeScheduleModal();
      } catch (error: any) {
        console.error('Failed to schedule interview:', error);
        if (error.response?.data?.errors) {
          console.error('Validation errors:', error.response.data.errors);
        }
      }
    }}
    candidateName=""
    applicationId={0}
    selectedDate={selectedDateForScheduling}
    interviews={interviews}
    availableTimeSlots={getAvailableTimeSlots(selectedDateForScheduling, interviews)}
  />
)}
    </div>
  );
}

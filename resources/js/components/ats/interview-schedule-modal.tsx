// InterviewScheduleModal.tsx
import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Calendar, Clock, MapPin } from 'lucide-react';
import type { InterviewLocationType } from '@/types/ats-pages';

interface ScheduleInterviewData {
  scheduled_date: string;
  scheduled_time: string;
  duration_minutes: number;
  location_type: InterviewLocationType;
}

interface InterviewScheduleModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: ScheduleInterviewData) => Promise<void>;
  candidateName?: string;
  applicationId?: number;
  scheduledDate?: string;
  scheduledTime?: string;
  availableTimeSlots?: string[];
}

export function InterviewScheduleModal({
  isOpen,
  onClose,
  onSubmit,
  candidateName = 'Candidate',
  scheduledDate,
  scheduledTime,
  availableTimeSlots = [],
}: InterviewScheduleModalProps) {
  const [formData, setFormData] = useState<ScheduleInterviewData>({
    scheduled_date: scheduledDate || '',
    scheduled_time: scheduledTime || '',
    duration_minutes: 30,
    location_type: 'office',
  });
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setFormData((prev) => ({
        ...prev,
        scheduled_date: scheduledDate || '',
        scheduled_time: scheduledTime || '',
      }));
    }
  }, [isOpen, scheduledDate, scheduledTime]);

  const handleSubmit = async () => {
    if (!formData.scheduled_date || !formData.scheduled_time) return;
    setIsLoading(true);
    try {
      await onSubmit(formData);
      onClose();
      setFormData({
        scheduled_date: '',
        scheduled_time: '',
        duration_minutes: 30,
        location_type: 'office',
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{scheduledDate && scheduledTime ? 'Reschedule Interview' : 'Schedule Interview'}</DialogTitle>
          <DialogDescription>
            {scheduledDate && scheduledTime
              ? `Reschedule interview for ${candidateName}`
              : `Schedule a new interview for ${candidateName}`}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="scheduled_date" className="flex items-center gap-2"><Calendar className="h-4 w-4" />Date</Label>
            <Input
              id="scheduled_date"
              type="date"
              value={formData.scheduled_date}
              onChange={(e) => setFormData((prev) => ({ ...prev, scheduled_date: e.target.value }))}
              disabled={isLoading}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="scheduled_time" className="flex items-center gap-2"><Clock className="h-4 w-4" />Time</Label>
            {availableTimeSlots.length > 0 ? (
              <Select
                value={formData.scheduled_time}
                onValueChange={(value) => setFormData((prev) => ({ ...prev, scheduled_time: value }))}
                disabled={isLoading}
              >
                <SelectTrigger id="scheduled_time">
                  <SelectValue placeholder="Select available time" />
                </SelectTrigger>
                <SelectContent>
                  {availableTimeSlots.map((slot) => (
                    <SelectItem key={slot} value={slot}>{slot}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <div className="p-3 rounded-md bg-yellow-50 border border-yellow-200">
                <p className="text-sm text-yellow-800">No available time slots on this day</p>
              </div>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="duration_minutes">Duration (minutes)</Label>
            <Select
              value={formData.duration_minutes.toString()}
              onValueChange={(value) => setFormData((prev) => ({ ...prev, duration_minutes: parseInt(value) }))}
              disabled={isLoading}
            >
              <SelectTrigger id="duration_minutes"><SelectValue placeholder="Select duration" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="30">30 minutes</SelectItem>
                <SelectItem value="45">45 minutes</SelectItem>
                <SelectItem value="60">1 hour</SelectItem>
                <SelectItem value="90">1.5 hours</SelectItem>
                <SelectItem value="120">2 hours</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="location_type" className="flex items-center gap-2"><MapPin className="h-4 w-4" />Location Type</Label>
            <Select
              value={formData.location_type}
              onValueChange={(value) => setFormData((prev) => ({ ...prev, location_type: value as InterviewLocationType }))}
              disabled={isLoading}
            >
              <SelectTrigger id="location_type"><SelectValue placeholder="Select location" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="office">Office</SelectItem>
                <SelectItem value="phone">Phone</SelectItem>
                <SelectItem value="video">Video Conference</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={isLoading}>Cancel</Button>
          <Button
            onClick={handleSubmit}
            disabled={!formData.scheduled_date || !formData.scheduled_time || isLoading}
          >
            {scheduledDate && scheduledTime ? 'Reschedule' : 'Schedule Interview'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

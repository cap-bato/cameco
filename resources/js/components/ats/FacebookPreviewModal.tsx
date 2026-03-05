import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Facebook } from 'lucide-react';
import axios from 'axios';
import type { JobPosting } from '@/types/ats-pages';

interface FacebookPreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  jobPosting: JobPosting;
  onConfirm: () => void;
}

interface PreviewData {
  message: string;
  link: string;
}

/**
 * Facebook Preview Modal Component
 * Displays a preview of how a job posting will appear on Facebook before publishing
 */
export function FacebookPreviewModal({
  isOpen,
  onClose,
  jobPosting,
  onConfirm,
}: FacebookPreviewModalProps) {
  const [preview, setPreview] = useState<PreviewData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && jobPosting) {
      fetchPreview();
    }
  }, [isOpen, jobPosting?.id]);

  const fetchPreview = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get<{ preview: PreviewData }>(
        `/hr/ats/job-postings/${jobPosting.id}/facebook-preview`
      );
      setPreview(response.data.preview);
    } catch (error: any) {
      console.error('Failed to fetch preview:', error);
      const errorMessage = error.response?.data?.message || 'Failed to load preview';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Facebook className="h-5 w-5 text-blue-600" />
            Facebook Post Preview
          </DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className="py-8 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p className="mt-4 text-muted-foreground">Loading preview...</p>
          </div>
        ) : error ? (
          <div className="py-8 text-center space-y-4">
            <p className="text-destructive">{error}</p>
            <Button variant="outline" onClick={fetchPreview}>
              Try Again
            </Button>
          </div>
        ) : preview ? (
          <div className="space-y-4">
            {/* Facebook Post Mockup */}
            <div className="bg-white border rounded-lg p-4 shadow-sm">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                  <Facebook className="h-6 w-6 text-white" />
                </div>
                <div>
                  <div className="font-semibold">Cathay Metal Corporation</div>
                  <div className="text-xs text-gray-500">Just now · Public</div>
                </div>
              </div>

              <div className="whitespace-pre-wrap text-sm mb-3">
                {preview.message}
              </div>

              <div className="border rounded bg-gray-50 p-3">
                <div className="text-xs text-gray-500 uppercase mb-1">Link Preview</div>
                <div className="font-medium text-blue-600 break-all">{preview.link}</div>
              </div>
            </div>

            {/* Actions */}
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={onClose}>
                Cancel
              </Button>
              <Button onClick={onConfirm} className="gap-2">
                <Facebook className="h-4 w-4" />
                Post to Facebook
              </Button>
            </div>
          </div>
        ) : (
          <div className="py-8 text-center text-muted-foreground">
            Failed to load preview
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

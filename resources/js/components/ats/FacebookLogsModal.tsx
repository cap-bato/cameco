import React, { useState, useEffect, useCallback } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Facebook, ThumbsUp, MessageCircle, Share2, RefreshCw } from 'lucide-react';
import axios, { AxiosError } from 'axios';
import type { JobPosting } from '@/types/ats-pages';

interface FacebookLog {
  id: number;
  facebook_post_id: string | null;
  facebook_post_url: string | null;
  post_type: 'manual' | 'auto';
  status: 'pending' | 'posted' | 'failed';
  error_message?: string | null;
  engagement_metrics?: {
    likes: number;
    comments: number;
    shares: number;
    fetched_at: string;
  } | null;
  posted_by_name?: string;
  created_at: string;
}

interface FacebookLogsResponse {
  logs: FacebookLog[];
}

interface FacebookLogsModalProps {
  isOpen: boolean;
  onClose: () => void;
  jobPosting: JobPosting;
}

/**
 * Facebook Logs Modal Component
 * Displays Facebook posting history and engagement metrics for a job posting
 */
export function FacebookLogsModal({
  isOpen,
  onClose,
  jobPosting,
}: FacebookLogsModalProps) {
  const [logs, setLogs] = useState<FacebookLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchLogs = useCallback(async () => {
    setLoading(true);
    try {
      const response = await axios.get<FacebookLogsResponse>(
        `/hr/ats/job-postings/${jobPosting.id}/facebook-logs`
      );
      setLogs(response.data.logs);
    } catch (error: unknown) {
      console.error('Failed to fetch logs:', error);
      let errorMessage = 'Failed to load Facebook logs';
      if (error instanceof AxiosError && error.response?.data?.message) {
        errorMessage = error.response.data.message;
      }
      alert(errorMessage);
    } finally {
      setLoading(false);
    }
  }, [jobPosting.id]);

  useEffect(() => {
    if (isOpen) {
      fetchLogs();
    }
  }, [isOpen, fetchLogs]);

  const refreshMetrics = async () => {
    setRefreshing(true);
    try {
      await axios.post(`/hr/ats/job-postings/${jobPosting.id}/refresh-facebook-metrics`);
      await fetchLogs();
      alert('Engagement metrics updated successfully!');
    } catch (error: unknown) {
      let errorMessage = 'Failed to refresh metrics';
      if (error instanceof AxiosError && error.response?.data?.message) {
        errorMessage = error.response.data.message;
      }
      alert(errorMessage);
    } finally {
      setRefreshing(false);
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle className="flex items-center justify-between">
            <span className="flex items-center gap-2">
              <Facebook className="h-5 w-5 text-blue-600" />
              Facebook Post History
            </span>
            {jobPosting.facebook_post_id && (
              <Button
                variant="outline"
                size="sm"
                onClick={refreshMetrics}
                disabled={refreshing}
                className="gap-2"
              >
                <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                Refresh Metrics
              </Button>
            )}
          </DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className="py-8 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p className="mt-4 text-muted-foreground">Loading logs...</p>
          </div>
        ) : logs.length === 0 ? (
          <div className="py-8 text-center space-y-2">
            <Facebook className="h-12 w-12 text-muted-foreground mx-auto opacity-50" />
            <p className="text-muted-foreground">No Facebook posts yet</p>
            <p className="text-sm text-muted-foreground">
              Post this job to Facebook to see activity here
            </p>
          </div>
        ) : (
          <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
            {logs.map((log) => (
              <div key={log.id} className="border rounded-lg p-4 space-y-3">
                <div className="flex items-start justify-between">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Badge
                        variant={
                          log.status === 'posted'
                            ? 'default'
                            : log.status === 'failed'
                            ? 'destructive'
                            : 'secondary'
                        }
                      >
                        {log.status.toUpperCase()}
                      </Badge>
                      <Badge variant="outline">{log.post_type}</Badge>
                    </div>
                    <div className="text-sm text-muted-foreground">
                      Posted by {log.posted_by_name || 'Unknown'} · {formatDate(log.created_at)}
                    </div>
                  </div>

                  {log.facebook_post_url && (
                    <a
                      href={log.facebook_post_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-blue-600 hover:underline text-sm flex items-center gap-1"
                    >
                      <Facebook className="h-3 w-3" />
                      View Post →
                    </a>
                  )}
                </div>

                {log.error_message && (
                  <div className="bg-destructive/10 border border-destructive/30 rounded-md p-3">
                    <p className="text-sm text-destructive font-medium">Error:</p>
                    <p className="text-sm text-destructive mt-1">{log.error_message}</p>
                  </div>
                )}

                {log.engagement_metrics && (
                  <div className="bg-accent rounded-md p-3">
                    <div className="text-xs text-muted-foreground mb-2">
                      Engagement updated {formatDate(log.engagement_metrics.fetched_at)}
                    </div>
                    <div className="flex gap-4 text-sm">
                      <div className="flex items-center gap-1.5">
                        <ThumbsUp className="h-4 w-4 text-blue-600" />
                        <span className="font-medium">{log.engagement_metrics.likes}</span>
                        <span className="text-muted-foreground">Likes</span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <MessageCircle className="h-4 w-4 text-green-600" />
                        <span className="font-medium">{log.engagement_metrics.comments}</span>
                        <span className="text-muted-foreground">Comments</span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <Share2 className="h-4 w-4 text-purple-600" />
                        <span className="font-medium">{log.engagement_metrics.shares}</span>
                        <span className="text-muted-foreground">Shares</span>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

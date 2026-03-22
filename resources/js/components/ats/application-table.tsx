import { Link } from '@inertiajs/react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Eye, Check, X, Calendar } from 'lucide-react';
import { ApplicationStatusBadge } from './application-status-badge';
import { PermissionGate } from '@/components/permission-gate';
import type { Application } from '@/types/ats-pages';
import { formatDate } from '@/lib/date-utils';

interface ApplicationTableProps {
  applications: Application[];
  selectedIds?: Set<number>;
  onSelectApplication?: (id: number) => void;
  onSelectAll?: (checked: boolean) => void;
  onViewClick?: (application: Application) => void;
  onShortlistClick?: (application: Application) => void;
  onRejectClick?: (application: Application) => void;
  onScheduleInterviewClick?: (application: Application) => void;
}

/**
 * Application Table Component
 * Displays applications with status, applied date, score, and bulk actions
 */
export function ApplicationTable({
  applications,
  selectedIds = new Set(),
  onSelectApplication,
  onSelectAll,
  onViewClick,
  onShortlistClick,
  onRejectClick,
  onScheduleInterviewClick,
}: ApplicationTableProps) {
  const allSelected = applications.length > 0 && selectedIds.size === applications.length;
  return (
    <div className="rounded-lg border overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-[40px]">
              <input
                type="checkbox"
                checked={allSelected}
                onChange={(e) => onSelectAll?.(e.target.checked)}
                className="rounded border-gray-300"
              />
            </TableHead>
            <TableHead>Candidate Name</TableHead>
            <TableHead>Job Title</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Applied Date</TableHead>
            <TableHead className="text-right">Score</TableHead>
            <TableHead className="w-[50px]"></TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {applications.map((application) => (
            <TableRow key={application.id}>
              <TableCell>
                <input
                  type="checkbox"
                  checked={selectedIds.has(application.id)}
                  onChange={() => onSelectApplication?.(application.id)}
                  className="rounded border-gray-300"
                />
              </TableCell>
              <TableCell className="font-medium">
                <Link
                  href={`/hr/ats/applications/${application.id}`}
                  className="hover:underline text-blue-600"
                >
                  {application.candidate_name || 'Unknown'}
                </Link>
              </TableCell>
              <TableCell>{application.job_title || 'Untitled'}</TableCell>
              <TableCell>
                <ApplicationStatusBadge status={application.status} />
              </TableCell>
              <TableCell>{formatDate(application.applied_at)}</TableCell>
              <TableCell className="text-right">
                {application.score !== null && application.score !== undefined ? `${application.score}%` : 'N/A'}
              </TableCell>
              <TableCell>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="h-8 w-8 p-0">
                      <span className="sr-only">Open menu</span>
                      <MoreHorizontal className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => onViewClick?.(application)}>
                      <Eye className="mr-2 h-4 w-4" />
                      View
                    </DropdownMenuItem>
                    <PermissionGate permission="hr.ats.applications.update">
                      {application.status !== 'shortlisted' &&
                        application.status !== 'interviewed' &&
                        application.status !== 'offered' &&
                        application.status !== 'hired' && (
                          <DropdownMenuItem onClick={() => onShortlistClick?.(application)}>
                            <Check className="mr-2 h-4 w-4" />
                            Shortlist
                          </DropdownMenuItem>
                        )}
                      {application.status !== 'rejected' &&
                        application.status !== 'withdrawn' && (
                          <DropdownMenuItem onClick={() => onRejectClick?.(application)}>
                            <X className="mr-2 h-4 w-4" />
                            Reject
                          </DropdownMenuItem>
                        )}
                      {(application.status === 'shortlisted' ||
                        application.status === 'interviewed') && (
                        <DropdownMenuItem
                          onClick={() => onScheduleInterviewClick?.(application)}
                        >
                          <Calendar className="mr-2 h-4 w-4" />
                          Schedule Interview
                        </DropdownMenuItem>
                      )}
                    </PermissionGate>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

import type { ApplicationStatus } from '@/types/ats-pages';

interface ApplicationStatusBadgeProps {
  status: ApplicationStatus | null | undefined;
}

const statusConfig: Record<
  ApplicationStatus,
  { label: string; bgColor: string; textColor: string }
> = {
  submitted: { label: 'Submitted', bgColor: 'bg-blue-100', textColor: 'text-blue-800' },
  shortlisted: { label: 'Shortlisted', bgColor: 'bg-green-100', textColor: 'text-green-800' },
  interviewed: { label: 'Interviewed', bgColor: 'bg-purple-100', textColor: 'text-purple-800' },
  offered: { label: 'Offered', bgColor: 'bg-cyan-100', textColor: 'text-cyan-800' },
  hired: { label: 'Hired', bgColor: 'bg-emerald-100', textColor: 'text-emerald-800' },
  rejected: { label: 'Rejected', bgColor: 'bg-red-100', textColor: 'text-red-800' },
  withdrawn: { label: 'Withdrawn', bgColor: 'bg-gray-100', textColor: 'text-gray-800' },
};

export function ApplicationStatusBadge({ status }: ApplicationStatusBadgeProps) {
  const config = statusConfig[status as ApplicationStatus] ?? {
    label: 'New',
    bgColor: 'bg-yellow-100',
    textColor: 'text-yellow-800',
  };

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bgColor} ${config.textColor}`}
    >
      {config.label}
    </span>
  );
}

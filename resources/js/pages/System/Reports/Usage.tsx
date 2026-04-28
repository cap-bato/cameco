import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import { Users, Clock, Activity } from 'lucide-react';

type LoginStat = {
  user_id: number;
  user_name: string;
  email: string;
  login_count: number;
  last_login: string;
  first_login: string;
};

type Module = {
  module: string;
  access_count: number;
  percentage: number;
};

type SessionStat = {
  user_id: number;
  user_name: string;
  login_at: string;
  logout_at: string;
  duration_minutes: number;
};

type SessionStats = {
  total_sessions: number;
  average_duration_minutes: number;
  max_duration_minutes: number;
  min_duration_minutes: number;
  total_duration_hours: number;
  sessions: SessionStat[];
};

type ActivityByType = {
  action: string;
  count: number;
};

interface Props {
  activity_summary: {
    total_users: number;
    active_accounts: number;
    total_events: number;
    unique_users: number;
    successful_logins: number;
    failed_login_attempts: number;
    success_rate: number;
    period_start: string;
    period_end: string;
  };
  user_login_stats: LoginStat[];
  most_used_modules: Module[];
  session_stats: SessionStats;
  activity_by_type: ActivityByType[];
  from_date: string;
  to_date: string;
}

const COLORS = [
  '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
  '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#84cc16',
];

/**
 * Safely format a duration in minutes to a human-readable string.
 * Guards against negatives (backend bug) and long decimals.
 *
 * Examples:
 *   formatDuration(90)    → "1h 30m"
 *   formatDuration(45)    → "45m"
 *   formatDuration(-360)  → "6h 0m"   ← abs() safety net
 */
function formatDuration(rawMinutes: number): string {
  const minutes = Math.round(Math.abs(rawMinutes));
  if (minutes === 0) return '0m';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}m`;
  if (m === 0) return `${h}h`;
  return `${h}h ${m}m`;
}

/** Round a number to at most 1 decimal place, removing trailing .0 */
function fmt1(n: number): string {
  return Math.abs(parseFloat(n.toFixed(1))).toString();
}

export default function UsageAnalytics({
  activity_summary,
  user_login_stats,
  most_used_modules,
  session_stats,
  activity_by_type,
  from_date,
  to_date,
}: Props) {
  const [fromDate, setFromDate] = useState(from_date);
  const [toDate, setToDate] = useState(to_date);

  const handleDateFilter = () => {
    const params = new URLSearchParams({ from: fromDate, to: toDate });
    window.location.href = `/system/reports/usage?${params.toString()}`;
  };

  const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '#' },
    { title: 'Usage Analytics', href: '#' },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbItems}>
      <Head title="Usage Analytics" />

      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold dark:text-foreground">Usage Analytics</h1>
            <p className="text-muted-foreground mt-1">System activity and user engagement metrics</p>
          </div>
        </div>

        {/* Date Filter */}
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Date Range Filter</CardTitle>
          </CardHeader>
          <CardContent className="flex gap-4 items-end">
            <div className="flex-1">
              <label className="text-sm font-medium">From</label>
              <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} className="mt-1" />
            </div>
            <div className="flex-1">
              <label className="text-sm font-medium">To</label>
              <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} className="mt-1" />
            </div>
            <Button onClick={handleDateFilter}>Apply Filter</Button>
          </CardContent>
        </Card>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Activity className="h-4 w-4" />
                Total Events
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{activity_summary.total_events.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground mt-1">Activities logged</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Users className="h-4 w-4" />
                Active Accounts
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{activity_summary.active_accounts}</div>
              <p className="text-xs text-muted-foreground mt-1">
                {activity_summary.unique_users} users had activity in selected period
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Success Rate</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{fmt1(activity_summary.success_rate)}%</div>
              <p className="text-xs text-muted-foreground mt-1">
                {activity_summary.successful_logins} successful, {activity_summary.failed_login_attempts} failed
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Clock className="h-4 w-4" />
                Avg Session Duration
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">
                {formatDuration(session_stats.average_duration_minutes)}
              </div>
              <p className="text-xs text-muted-foreground mt-1">{session_stats.total_sessions} sessions</p>
            </CardContent>
          </Card>
        </div>

        {/* Most Used Modules */}
        <Card>
          <CardHeader>
            <CardTitle>Most Used Modules</CardTitle>
            <CardDescription>Module access frequency</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {most_used_modules.map((module, idx) => (
                <div key={idx}>
                  <div className="flex justify-between items-center text-sm mb-1">
                    <span className="font-medium">{module.module}</span>
                    <span className="text-muted-foreground">
                      {module.access_count} accesses ({fmt1(module.percentage)}%)
                    </span>
                  </div>
                  <div className="w-full bg-neutral-200 dark:bg-neutral-900 rounded h-2">
                    <div
                      className="bg-blue-500 h-2 rounded transition-all"
                      style={{ width: `${Math.min(100, Math.abs(module.percentage))}%` }}
                    />
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Activity by Type */}
        <Card>
          <CardHeader>
            <CardTitle>Activity by Type</CardTitle>
            <CardDescription>Action frequency breakdown (top 15)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {activity_by_type.map((action, idx) => (
                <div key={idx} className="flex items-center justify-between p-2 border rounded">
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 rounded" style={{ backgroundColor: COLORS[idx % COLORS.length] }} />
                    <span className="text-sm font-medium">{action.action}</span>
                  </div>
                  <Badge variant="secondary">{action.count}</Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* User Login Statistics */}
        <Card>
          <CardHeader>
            <CardTitle>User Login Statistics</CardTitle>
            <CardDescription>Last 20 most active users</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2">User</th>
                    <th className="text-right py-2 px-2">Logins</th>
                    <th className="text-left py-2 px-2">Last Login</th>
                    <th className="text-left py-2 px-2">First Login</th>
                  </tr>
                </thead>
                <tbody>
                  {user_login_stats.map((stat) => (
                    <tr key={stat.user_id} className="border-b hover:bg-gray-50 dark:hover:bg-slate-800">
                      <td className="py-2 px-2">
                        <div className="font-medium">{stat.user_name}</div>
                        <div className="text-xs text-muted-foreground">{stat.email}</div>
                      </td>
                      <td className="text-right py-2 px-2">
                        <Badge variant="secondary">{stat.login_count}</Badge>
                      </td>
                      <td className="py-2 px-2 text-xs">{new Date(stat.last_login).toLocaleString()}</td>
                      <td className="py-2 px-2 text-xs">{new Date(stat.first_login).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        {/* Session Duration Statistics */}
        <Card>
          <CardHeader>
            <CardTitle>Session Duration Statistics</CardTitle>
            <CardDescription>User session metrics</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="border rounded p-3">
                <p className="text-sm text-muted-foreground">Total Sessions</p>
                <p className="text-2xl font-bold">{session_stats.total_sessions}</p>
              </div>
              <div className="border rounded p-3">
                <p className="text-sm text-muted-foreground">Average Duration</p>
                <p className="text-2xl font-bold">
                  {formatDuration(session_stats.average_duration_minutes)}
                </p>
              </div>
              <div className="border rounded p-3">
                <p className="text-sm text-muted-foreground">Max Duration</p>
                <p className="text-2xl font-bold">
                  {formatDuration(session_stats.max_duration_minutes)}
                </p>
              </div>
              <div className="border rounded p-3">
                <p className="text-sm text-muted-foreground">Total Hours</p>
                <p className="text-2xl font-bold">
                  {fmt1(Math.abs(session_stats.total_duration_hours))}h
                </p>
              </div>
            </div>

            {session_stats.sessions.length > 0 && (
              <div className="mt-6">
                <h4 className="font-semibold text-sm mb-3">Longest Sessions</h4>
                <div className="space-y-2 max-h-64 overflow-y-auto">
                  {session_stats.sessions.map((session, idx) => (
                    <div key={idx} className="flex justify-between items-center text-sm p-2 border rounded">
                      <div>
                        <p className="font-medium">{session.user_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {new Date(session.login_at).toLocaleString()} → {new Date(session.logout_at).toLocaleString()}
                        </p>
                      </div>
                      <Badge>{formatDuration(session.duration_minutes)}</Badge>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
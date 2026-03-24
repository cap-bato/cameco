import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, AlertCircle, AlertTriangle, Info, CheckCircle2 } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
}

interface ErrorLog {
    id: number;
    level: string;
    message: string;
    exception_class: string | null;
    exception_message: string | null;
    stack_trace: string | null;
    file: string | null;
    line: number | null;
    short_file?: string;
    url: string | null;
    method: string | null;
    ip_address: string | null;
    is_resolved: boolean;
    resolution_notes: string | null;
    resolved_at: string | null;
    created_at: string;
    user?: User;
    resolver?: User;
}

interface Props {
    errorLog: ErrorLog;
    similarErrors: ErrorLog[];
}

function LevelBadge({ level }: { level: string }) {
    switch (level) {
        case 'critical':
            return (
                <Badge className="bg-red-500">
                    <AlertCircle className="mr-1 h-3 w-3" />
                    Critical
                </Badge>
            );
        case 'error':
            return (
                <Badge className="bg-orange-500">
                    <AlertTriangle className="mr-1 h-3 w-3" />
                    Error
                </Badge>
            );
        case 'warning':
            return (
                <Badge className="bg-yellow-500 text-black">
                    <AlertTriangle className="mr-1 h-3 w-3" />
                    Warning
                </Badge>
            );
        default:
            return (
                <Badge className="bg-blue-500">
                    <Info className="mr-1 h-3 w-3" />
                    {level}
                </Badge>
            );
    }
}

export default function ErrorLogDetail({ errorLog, similarErrors }: Props) {
    const handleResolve = () => {
        if (errorLog.is_resolved) {
            return;
        }

        const notes = prompt('Resolution notes:');
        if (!notes) {
            return;
        }

        router.post(`/system/logs/errors/${errorLog.id}/resolve`, {
            resolution_notes: notes,
        });
    };

    const handleBulkResolve = () => {
        if (!errorLog.exception_class || !errorLog.exception_message) {
            return;
        }

        const notes = prompt('Resolution notes for similar errors:');
        if (!notes) {
            return;
        }

        router.post(`/system/logs/errors/${errorLog.id}/bulk-resolve`, {
            resolution_notes: notes,
        });
    };

    return (
        <AppLayout>
            <Head title={`Error #${errorLog.id}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href="/system/logs/errors">
                            <Button variant="outline" size="sm" className="gap-2">
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold">Error Log Detail</h1>
                            <p className="text-sm text-muted-foreground">Entry #{errorLog.id}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {!errorLog.is_resolved && (
                            <Button onClick={handleResolve}>Resolve</Button>
                        )}
                        {!errorLog.is_resolved && similarErrors.length > 0 && (
                            <Button variant="outline" onClick={handleBulkResolve}>
                                Resolve Similar ({similarErrors.length})
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <LevelBadge level={errorLog.level} />
                            {errorLog.is_resolved && (
                                <Badge className="bg-green-500">
                                    <CheckCircle2 className="mr-1 h-3 w-3" />
                                    Resolved
                                </Badge>
                            )}
                            <span className="text-sm text-muted-foreground">
                                {new Date(errorLog.created_at).toLocaleString()}
                            </span>
                        </div>

                        <div>
                            <p className="font-medium">{errorLog.message}</p>
                            {errorLog.exception_message && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {errorLog.exception_message}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="text-sm">
                                <span className="font-semibold">Exception:</span>{' '}
                                {errorLog.exception_class ?? 'N/A'}
                            </div>
                            <div className="text-sm">
                                <span className="font-semibold">File:</span>{' '}
                                {errorLog.short_file ?? errorLog.file ?? 'N/A'}
                                {errorLog.line ? `:${errorLog.line}` : ''}
                            </div>
                            <div className="text-sm">
                                <span className="font-semibold">URL:</span> {errorLog.url ?? 'N/A'}
                            </div>
                            <div className="text-sm">
                                <span className="font-semibold">Method:</span> {errorLog.method ?? 'N/A'}
                            </div>
                            <div className="text-sm">
                                <span className="font-semibold">IP:</span> {errorLog.ip_address ?? 'N/A'}
                            </div>
                            <div className="text-sm">
                                <span className="font-semibold">User:</span>{' '}
                                {errorLog.user ? `${errorLog.user.name} (${errorLog.user.email})` : 'Guest / System'}
                            </div>
                        </div>

                        {errorLog.resolution_notes && (
                            <div className="rounded border border-green-200 bg-green-50 p-3 text-sm dark:border-green-900 dark:bg-green-950/30">
                                <div className="font-semibold">Resolution Notes</div>
                                <div className="mt-1">{errorLog.resolution_notes}</div>
                                {errorLog.resolver && (
                                    <div className="mt-1 text-muted-foreground">
                                        Resolved by {errorLog.resolver.name}
                                        {errorLog.resolved_at ? ` on ${new Date(errorLog.resolved_at).toLocaleString()}` : ''}
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Stack Trace</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="max-h-[420px] overflow-auto rounded bg-muted p-3 text-xs leading-relaxed">
                            {errorLog.stack_trace || 'No stack trace available.'}
                        </pre>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Similar Errors</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {similarErrors.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No similar errors found.</p>
                        ) : (
                            <div className="space-y-2">
                                {similarErrors.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between rounded border p-3">
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium">#{item.id} {item.message}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(item.created_at).toLocaleString()}
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(`/system/logs/errors/${item.id}`)}
                                        >
                                            View
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Cell,
} from 'recharts';
import { TrendingUp } from 'lucide-react';

interface DailyScan {
    date: string;
    scans: number;
}

interface HourlyPeakData {
    hour: number;
    scans: number;
}

interface DeviceUsageData {
    device: string;
    scans: number;
}

interface BadgeAnalyticsProps {
    dailyScans: DailyScan[];
    hourlyPeaks: HourlyPeakData[];
    deviceUsage: DeviceUsageData[];
}

// Color palette for charts
const COLORS = [
    '#3b82f6', // blue
    '#06b6d4', // cyan
    '#10b981', // emerald
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
];

export function BadgeAnalytics({
    dailyScans,
    hourlyPeaks,
    deviceUsage,
}: BadgeAnalyticsProps) {
    // Calculate peak hour
    const peakHour = hourlyPeaks.length > 0
        ? hourlyPeaks.reduce((prev, current) =>
              prev.scans > current.scans ? prev : current
          )
        : null;

    // Calculate total scans in analytics period
    const totalScans = dailyScans.reduce((sum, day) => sum + day.scans, 0);

    // Calculate average scans per day
    const avgScansPerDay = dailyScans.length > 0
        ? (totalScans / dailyScans.length).toFixed(1)
        : '0';

    return (
        <div className="space-y-6">
            {/* Analytics Summary */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium">
                            Total Scans (7 Days)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{totalScans}</p>
                        <p className="text-xs text-muted-foreground mt-1">
                            Scans in the last 7 days
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium">
                            Average Scans/Day
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{avgScansPerDay}</p>
                        <p className="text-xs text-muted-foreground mt-1">
                            Average across all days
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium">
                            Peak Hour
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">
                            {peakHour ? `${String(peakHour.hour).padStart(2, '0')}:00` : 'N/A'}
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">
                            {peakHour ? `${peakHour.scans} scans` : 'No data'}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Scans per Day - Bar Chart */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <TrendingUp className="h-5 w-5" />
                        Scans per Day (7-Day Trend)
                    </CardTitle>
                    <CardDescription>
                        Daily scan activity over the past week
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {dailyScans.length > 0 ? (
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={dailyScans}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="date" />
                                <YAxis />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'rgb(20, 20, 20)',
                                        border: '1px solid rgb(100, 100, 100)',
                                        borderRadius: '4px',
                                    }}
                                />
                                <Bar dataKey="scans" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    ) : (
                        <div className="h-80 flex items-center justify-center text-muted-foreground">
                            No data available
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Peak Hours - Heatmap-like visualization */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        Peak Hours Distribution
                    </CardTitle>
                    <CardDescription>
                        Scan activity by hour of day
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {hourlyPeaks.length > 0 ? (
                        <div className="space-y-4">
                            {/* Heat grid visualization */}
                            <div className="grid grid-cols-12 gap-1">
                                {hourlyPeaks.map((peak) => {
                                    const maxScans = Math.max(
                                        ...hourlyPeaks.map((p) => p.scans)
                                    );
                                    const intensity = (peak.scans / maxScans) * 100;
                                    const bgColor =
                                        intensity > 75
                                            ? 'bg-red-500'
                                            : intensity > 50
                                              ? 'bg-orange-500'
                                              : intensity > 25
                                                ? 'bg-yellow-500'
                                                : 'bg-blue-500';

                                    return (
                                        <div
                                            key={peak.hour}
                                            className="flex flex-col items-center"
                                        >
                                            <div
                                                className={`w-full aspect-square rounded ${bgColor} opacity-${Math.ceil(intensity / 10) * 10}`}
                                                title={`${String(peak.hour).padStart(2, '0')}:00 - ${peak.scans} scans`}
                                                style={{
                                                    opacity: Math.max(0.3, intensity / 100),
                                                }}
                                            />
                                            <span className="text-xs mt-1 w-full text-center">
                                                {String(peak.hour).padStart(2, '0')}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Legend */}
                            <div className="mt-4 pt-4 border-t">
                                <p className="text-xs font-medium mb-2">Scan Intensity:</p>
                                <div className="flex items-center gap-4 text-xs">
                                    <div className="flex items-center gap-1">
                                        <div className="w-4 h-4 bg-blue-500 rounded"></div>
                                        <span>Low</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-4 h-4 bg-yellow-500 rounded"></div>
                                        <span>Medium</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-4 h-4 bg-orange-500 rounded"></div>
                                        <span>High</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-4 h-4 bg-red-500 rounded"></div>
                                        <span>Peak</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="h-80 flex items-center justify-center text-muted-foreground">
                            No data available
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Most Used Devices - Horizontal Bar Chart */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        Most Used Devices
                    </CardTitle>
                    <CardDescription>
                        Scanner locations with highest scan activity
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {deviceUsage.length > 0 ? (
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart
                                layout="vertical"
                                data={deviceUsage}
                                margin={{ top: 5, right: 30, left: 200, bottom: 5 }}
                            >
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis type="number" />
                                <YAxis dataKey="device" type="category" width={190} />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'rgb(20, 20, 20)',
                                        border: '1px solid rgb(100, 100, 100)',
                                        borderRadius: '4px',
                                    }}
                                />
                                <Bar dataKey="scans" fill="#06b6d4" radius={[0, 8, 8, 0]}>
                                    {deviceUsage.map((entry, index) => (
                                        <Cell
                                            key={`cell-${index}`}
                                            fill={COLORS[index % COLORS.length]}
                                        />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    ) : (
                        <div className="h-80 flex items-center justify-center text-muted-foreground">
                            No data available
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

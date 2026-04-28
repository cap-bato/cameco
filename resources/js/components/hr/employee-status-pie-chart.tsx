import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface StatusData {
    status: string;
    status_key: string;
    count: number;
    percentage: number;
}

interface Props {
    data: StatusData[];
}

const COLORS = [
    '#3b82f6', // blue
    '#10b981', // emerald
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#14b8a6', // teal
    '#eab308', // lime
    '#6366f1', // indigo
];

export default function EmployeeStatusPieChart({ data }: Props) {
    const chartData = data.map((status, idx) => ({
        name: status.status,
        value: status.count,
        color: COLORS[idx % COLORS.length],
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Employee Status (Pie)</CardTitle>
                <CardDescription>Distribution by employment status</CardDescription>
            </CardHeader>
            <CardContent>
                {chartData.length > 0 ? (
                    <div className="w-full h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={chartData}
                                    dataKey="value"
                                    nameKey="name"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={90}
                                    label
                                >
                                    {chartData.map((entry, idx) => (
                                        <Cell key={`cell-${idx}`} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                ) : (
                    <div className="flex items-center justify-center h-80 text-gray-500">
                        <p>No status data available</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

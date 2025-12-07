import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Download, AlertCircle } from 'lucide-react';
import { useState } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

interface BIR2316SectionProps {
    currentYear: number;
    onDownload: (year: number) => Promise<void>;
}

// ============================================================================
// Component
// ============================================================================

export function BIR2316Section({
    currentYear,
    onDownload,
}: BIR2316SectionProps) {
    const [selectedYear, setSelectedYear] = useState(currentYear - 1);
    const [isDownloading, setIsDownloading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);

    // Check if current month is Jan or Feb
    const currentMonth = new Date().getMonth();
    const isBIRFilingPeriod = currentMonth === 0 || currentMonth === 1; // 0 = January, 1 = February

    // Generate list of available years (current year - 1 and 2 years back)
    const availableYears = Array.from({ length: 3 }, (_, i) => currentYear - 1 - i);

    const handleDownload = async () => {
        setIsDownloading(true);
        setError(null);
        setSuccess(false);

        try {
            await onDownload(selectedYear);
            setSuccess(true);
            setTimeout(() => setSuccess(false), 5000);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'An error occurred while downloading';
            setError(message);
        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Download className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                    BIR 2316 Tax Certificate
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {/* Availability Notice */}
                    {!isBIRFilingPeriod && (
                        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-900/10">
                            <div className="flex gap-3">
                                <AlertCircle className="h-5 w-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-yellow-800 dark:text-yellow-200">
                                        Available January - February Only
                                    </p>
                                    <p className="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                                        BIR 2316 tax certificates are available during the annual filing period.
                                        Please return in January or February to download your certificate.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Year Selection */}
                    <div>
                        <label className="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Select Year
                        </label>
                        <select
                            value={selectedYear}
                            onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                            disabled={isDownloading}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {availableYears.map((year) => (
                                <option key={year} value={year}>
                                    {year} Tax Year
                                </option>
                            ))}
                        </select>
                        <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            Select the tax year for which you want to download the BIR 2316 certificate
                        </p>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-900/10">
                            <p className="text-sm text-red-800 dark:text-red-200">
                                {error}
                            </p>
                        </div>
                    )}

                    {/* Success Message */}
                    {success && (
                        <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-900/10">
                            <p className="text-sm text-green-800 dark:text-green-200">
                                ✓ BIR 2316 has been downloaded successfully
                            </p>
                        </div>
                    )}

                    {/* Download Button */}
                    <div className="flex gap-3">
                        <Button
                            onClick={handleDownload}
                            disabled={isDownloading || !isBIRFilingPeriod}
                            className="flex-1"
                        >
                            <Download className="mr-2 h-4 w-4" />
                            {isDownloading ? 'Downloading...' : 'Download BIR 2316'}
                        </Button>
                    </div>

                    {/* Info Box */}
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                        <h4 className="font-semibold text-blue-900 dark:text-blue-100 text-sm mb-2">
                            What is BIR 2316?
                        </h4>
                        <ul className="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                            <li>• Official tax certificate issued by your employer</li>
                            <li>• Contains your total compensation and tax withheld</li>
                            <li>• Required for annual income tax filing with BIR</li>
                            <li>• Should be filed with your annual tax return (ITR)</li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

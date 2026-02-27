import React, { useState } from "react";
// ...existing code...
import { Download } from "lucide-react";
// Import Shadcn Skeleton if available
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

// Utility: isPreviewable
export function isPreviewable(mime: string): boolean {
    return mime === "application/pdf" || mime.startsWith("image/");
}

// Props interface
export interface DocumentPreviewProps {
    previewUrl: string;
    mimeType: string;
    fileName: string;
    downloadUrl?: string;
    className?: string;
}

export const DocumentPreview = ({ previewUrl, mimeType, fileName, downloadUrl, className }: DocumentPreviewProps) => {
    const [loading, setLoading] = useState<'idle' | 'loading' | 'loaded' | 'error'>('loading');

    // Fallback for unsupported types (DOCX and others)
    if (
        mimeType === "application/vnd.openxmlformats-officedocument.wordprocessingml.document" ||
        !isPreviewable(mimeType)
    ) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 bg-gray-50 rounded border border-dashed border-gray-300">
                <span className="text-gray-500 text-lg font-semibold mb-2">Preview not available</span>
                <span className="text-gray-400 mb-4">This file type cannot be previewed. Please download to view.</span>
                {downloadUrl && (
                    <a
                        href={downloadUrl}
                        download={fileName}
                        className="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                    >
                        Download
                    </a>
                )}
            </div>
        );
    }

    // PDF preview with loading and error state
    if (mimeType === "application/pdf") {
        if (loading === 'error') {
            return (
                <Alert variant="destructive" className="w-full h-[500px] flex flex-col items-center justify-center">
                    <AlertDescription>
                        Could not load preview. Try downloading the file instead.
                    </AlertDescription>
                    {downloadUrl && (
                        <Button variant="outline" className="mt-4" asChild>
                            <a href={downloadUrl} download={fileName}>
                                <Download className="mr-2 h-4 w-4" /> Download
                            </a>
                        </Button>
                    )}
                </Alert>
            );
        }
        return (
            <div className="relative w-full h-full min-h-[500px]">
                {loading === 'loading' && (
                    <Skeleton className="w-full h-[500px]" />
                )}
                <iframe
                    src={previewUrl}
                    className={className || "w-full h-full min-h-[500px] border-0 rounded"}
                    title={fileName}
                    style={{ display: loading === 'loaded' ? 'block' : 'none' }}
                    onLoad={() => setLoading('loaded')}
                    onError={() => setLoading('error')}
                />
            </div>
        );
    }

    // Image preview with loading and error state
    if (mimeType.startsWith("image/")) {
        if (loading === 'error') {
            return (
                <Alert variant="destructive" className="w-full h-[500px] flex flex-col items-center justify-center">
                    <AlertDescription>
                        Could not load preview. Try downloading the file instead.
                    </AlertDescription>
                    {downloadUrl && (
                        <Button variant="outline" className="mt-4" asChild>
                            <a href={downloadUrl} download={fileName}>
                                <Download className="mr-2 h-4 w-4" /> Download
                            </a>
                        </Button>
                    )}
                </Alert>
            );
        }
        // Remove setError function entirely, as it's not needed.
        // Instead, update the onError handler in the <img> tag to only call setLoading('error').

        // Remove setError and use setLoading directly, since setError is not needed.
        // Replace the setError call in onError with setLoading('error').

        // (No function needed here; just update the onError handler below.)

        return (
            <div className="flex items-center justify-center h-full p-4 bg-gray-50 rounded relative">
                {loading === 'loading' && (
                    <Skeleton className="w-full h-[500px] absolute top-0 left-0" />
                )}
                <img
                    src={previewUrl}
                    alt={fileName}
                    className="max-w-full max-h-[600px] object-contain rounded shadow"
                    style={{ display: loading === 'loaded' ? 'block' : 'none' }}
                    onLoad={() => setLoading('loaded')}
                        onError={() => setLoading('error')}
                />
            </div>
        );
    }

    // Should not reach here, but fallback
    return null;
};

import { useEffect, useRef, useState } from 'react';

interface UseDocumentPreviewResult {
  status: 'idle' | 'loading' | 'loaded' | 'error';
  contentType: string | null;
  objectUrl: string | null;
  error?: string;
}

/**
 * useDocumentPreview hook
 * Fetches previewUrl, detects actual mime type, returns blob URL for preview.
 * Cleans up blob URL on unmount.
 */
export function useDocumentPreview(previewUrl: string): UseDocumentPreviewResult {
  const [status, setStatus] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
  const [contentType, setContentType] = useState<string | null>(null);
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | undefined>(undefined);
  const urlRef = useRef<string | null>(null);

  // Reset state immediately when previewUrl changes and is falsy
  if (!previewUrl && status !== 'idle') {
    setStatus('idle');
    setContentType(null);
    setObjectUrl(null);
    setError(undefined);
  }

  useEffect(() => {
    if (!previewUrl) return;

    let cancelled = false;

    // Use a microtask to avoid synchronous setState in effect body
    Promise.resolve().then(() => {
      setStatus('loading');
      setContentType(null);
      setObjectUrl(null);
      setError(undefined);
    });

    fetch(previewUrl, {
      method: 'GET',
      headers: {
        'Accept': '*/*',
      },
      credentials: 'include',
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('Failed to fetch preview');
        }
        const mime = response.headers.get('Content-Type') || 'application/octet-stream';
        setContentType(mime);
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        urlRef.current = blobUrl;
        if (!cancelled) {
          setObjectUrl(blobUrl);
          setStatus('loaded');
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setStatus('error');
          setError(err.message || 'Could not load preview');
        }
      });

    return () => {
      cancelled = true;
      if (urlRef.current) {
        URL.revokeObjectURL(urlRef.current);
        urlRef.current = null;
      }
    };
  }, [previewUrl]);

  return {
    status,
    contentType,
    objectUrl,
    error,
  };
}

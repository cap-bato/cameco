import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, Loader2, CheckCircle } from 'lucide-react';

interface BadgeScannerModalProps {
    isOpen: boolean;
    onClose: () => void;
    onScanComplete: (cardUid: string, cardType: string) => void;
}

export function BadgeScannerModal({ isOpen, onClose, onScanComplete }: BadgeScannerModalProps) {
    const [scanStatus, setScanStatus] = useState<'scanning' | 'success' | 'error'>('scanning');
    const [scannedCard, setScannedCard] = useState<{ uid: string; type: string } | null>(null);

    // Simulate scanning process for Phase 1
    useEffect(() => {
        if (!isOpen) return;

        // Mock scan - wait 2 seconds then generate mock card data
        const scanTimer = setTimeout(() => {
            const mockCardTypes = ['mifare', 'desfire', 'em4100'];
            const randomType = mockCardTypes[Math.floor(Math.random() * mockCardTypes.length)];
            
            // Generate mock card UID in format XX:XX:XX:XX:XX
            const generateMockCardUid = () => {
                const bytes = [];
                for (let i = 0; i < 5; i++) {
                    bytes.push(Math.floor(Math.random() * 256).toString(16).padStart(2, '0').toUpperCase());
                }
                return bytes.join(':');
            };

            const mockCardUid = generateMockCardUid();
            setScannedCard({ uid: mockCardUid, type: randomType });
            setScanStatus('success');
        }, 2000);

        return () => clearTimeout(scanTimer);
    }, [isOpen]);

    const handleConfirm = () => {
        if (scannedCard) {
            onScanComplete(scannedCard.uid, scannedCard.type);
            handleClose();
        }
    };

    const handleClose = () => {
        setScanStatus('scanning');
        setScannedCard(null);
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Scan Badge</DialogTitle>
                    <DialogDescription>
                        {scanStatus === 'scanning' && 'Please hold badge near reader...'}
                        {scanStatus === 'success' && 'Badge scanned successfully ✅'}
                        {scanStatus === 'error' && 'Scan failed, please try again'}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center justify-center py-8">
                    {scanStatus === 'scanning' && (
                        <>
                            <Loader2 className="h-12 w-12 animate-spin text-blue-600 mb-4" />
                            <p className="text-sm text-muted-foreground text-center">
                                Scanning in progress (2 seconds)...
                            </p>
                        </>
                    )}

                    {scanStatus === 'success' && scannedCard && (
                        <>
                            <CheckCircle className="h-12 w-12 text-green-600 mb-4" />
                            <div className="space-y-3 w-full">
                                <div className="bg-muted rounded-lg p-3">
                                    <p className="text-xs text-muted-foreground mb-1">Card UID</p>
                                    <p className="font-mono text-sm font-semibold break-all">
                                        {scannedCard.uid}
                                    </p>
                                </div>
                                <div className="bg-muted rounded-lg p-3">
                                    <p className="text-xs text-muted-foreground mb-1">Detected Card Type</p>
                                    <p className="text-sm font-semibold capitalize">
                                        {scannedCard.type === 'mifare' && 'Mifare'}
                                        {scannedCard.type === 'desfire' && 'DESFire'}
                                        {scannedCard.type === 'em4100' && 'EM4100'}
                                    </p>
                                </div>
                            </div>
                        </>
                    )}

                    {scanStatus === 'error' && (
                        <>
                            <AlertCircle className="h-12 w-12 text-red-600 mb-4" />
                            <p className="text-sm text-muted-foreground text-center">
                                Failed to scan badge. Please try again.
                            </p>
                        </>
                    )}
                </div>

                <div className="flex gap-2 justify-end">
                    <Button variant="outline" onClick={handleClose}>
                        Cancel
                    </Button>
                    {scanStatus === 'success' && (
                        <Button onClick={handleConfirm} className="gap-2">
                            Use This Badge
                        </Button>
                    )}
                    {scanStatus === 'scanning' && (
                        <Button disabled>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Scanning...
                        </Button>
                    )}
                    {scanStatus === 'error' && (
                        <Button onClick={() => window.location.reload()} variant="destructive">
                            Try Again
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

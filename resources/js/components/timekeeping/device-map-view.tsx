import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import {  
    MapPin, 
    ZoomIn, 
    ZoomOut, 
    Maximize2,
    Info
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState, useRef } from 'react';
import { Device, DeviceStatus } from './device-status-dashboard';

interface DeviceMapViewProps {
    devices: Device[];
    onDeviceClick?: (deviceId: string) => void;
    className?: string;
}

/**
 * Device position on floor plan (x, y in percentage)
 */
interface DevicePosition {
    deviceId: string;
    x: number;
    y: number;
}

/**
 * Mock floor plan positions for devices
 */
const devicePositions: DevicePosition[] = [
    { deviceId: "GATE-01", x: 15, y: 20 },          // Main entrance, top-left
    { deviceId: "GATE-02", x: 85, y: 25 },          // Loading dock, top-right
    { deviceId: "CAFETERIA-01", x: 50, y: 70 },     // Cafeteria, center-bottom
    { deviceId: "OFFICE-01", x: 25, y: 50 },        // Office, left-middle
    { deviceId: "WAREHOUSE-01", x: 75, y: 55 },     // Warehouse, right-middle
    { deviceId: "PRODUCTION-01", x: 50, y: 35 },    // Production, center
];

/**
 * Get status color for map markers
 */
const getStatusColor = (status: DeviceStatus): string => {
    switch (status) {
        case 'online':
            return 'fill-green-500 stroke-green-600';
        case 'idle':
            return 'fill-yellow-500 stroke-yellow-600';
        case 'offline':
            return 'fill-red-500 stroke-red-600';
        case 'maintenance':
            return 'fill-blue-500 stroke-blue-600';
        default:
            return 'fill-gray-500 stroke-gray-600';
    }
};

/**
 * Get status glow effect
 */
const getStatusGlow = (status: DeviceStatus): string => {
    switch (status) {
        case 'online':
            return 'drop-shadow-[0_0_8px_rgba(34,197,94,0.6)]';
        case 'idle':
            return 'drop-shadow-[0_0_8px_rgba(234,179,8,0.6)]';
        case 'offline':
            return 'drop-shadow-[0_0_8px_rgba(239,68,68,0.6)]';
        case 'maintenance':
            return 'drop-shadow-[0_0_8px_rgba(59,130,246,0.6)]';
        default:
            return '';
    }
};

/**
 * Device Map View Component
 */
export function DeviceMapView({ devices, onDeviceClick, className }: DeviceMapViewProps) {
    const [zoom, setZoom] = useState(1);
    const [pan, setPan] = useState({ x: 0, y: 0 });
    const [isPanning, setIsPanning] = useState(false);
    const [lastPanPos, setLastPanPos] = useState({ x: 0, y: 0 });
    const mapRef = useRef<HTMLDivElement>(null);

    const handleZoomIn = () => {
        setZoom((prev) => Math.min(prev + 0.2, 2.5));
    };

    const handleZoomOut = () => {
        setZoom((prev) => Math.max(prev - 0.2, 0.5));
    };

    const handleReset = () => {
        setZoom(1);
        setPan({ x: 0, y: 0 });
    };

    const handleMouseDown = (e: React.MouseEvent) => {
        setIsPanning(true);
        setLastPanPos({ x: e.clientX, y: e.clientY });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!isPanning) return;
        
        const deltaX = e.clientX - lastPanPos.x;
        const deltaY = e.clientY - lastPanPos.y;
        
        setPan((prev) => ({
            x: prev.x + deltaX,
            y: prev.y + deltaY
        }));
        
        setLastPanPos({ x: e.clientX, y: e.clientY });
    };

    const handleMouseUp = () => {
        setIsPanning(false);
    };

    const getDevicePosition = (deviceId: string): DevicePosition | undefined => {
        return devicePositions.find((pos) => pos.deviceId === deviceId);
    };

    return (
        <Card className={cn('relative', className)}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <MapPin className="h-5 w-5" />
                            Device Floor Plan
                        </CardTitle>
                        <CardDescription>
                            Interactive map showing device locations and status
                        </CardDescription>
                    </div>

                    {/* Map Controls */}
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleZoomIn}
                            disabled={zoom >= 2.5}
                        >
                            <ZoomIn className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleZoomOut}
                            disabled={zoom <= 0.5}
                        >
                            <ZoomOut className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleReset}
                        >
                            <Maximize2 className="h-4 w-4" />
                        </Button>
                        <Badge variant="secondary" className="ml-2">
                            {Math.round(zoom * 100)}%
                        </Badge>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                {/* Map Legend */}
                <div className="flex items-center justify-center gap-6 mb-4 p-3 bg-slate-50 rounded-lg text-sm">
                    <div className="flex items-center gap-2">
                        <div className="w-3 h-3 rounded-full bg-green-500 border-2 border-green-600"></div>
                        <span>Online</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-3 h-3 rounded-full bg-yellow-500 border-2 border-yellow-600"></div>
                        <span>Idle</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-3 h-3 rounded-full bg-red-500 border-2 border-red-600"></div>
                        <span>Offline</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-3 h-3 rounded-full bg-blue-500 border-2 border-blue-600"></div>
                        <span>Maintenance</span>
                    </div>
                </div>

                {/* Floor Plan SVG */}
                <div 
                    ref={mapRef}
                    className={cn(
                        'relative w-full h-[600px] bg-slate-100 rounded-lg overflow-hidden border-2 border-slate-300',
                        isPanning ? 'cursor-grabbing' : 'cursor-grab'
                    )}
                    onMouseDown={handleMouseDown}
                    onMouseMove={handleMouseMove}
                    onMouseUp={handleMouseUp}
                    onMouseLeave={handleMouseUp}
                >
                    <svg
                        width="100%"
                        height="100%"
                        viewBox="0 0 1000 700"
                        className="select-none"
                        style={{
                            transform: `scale(${zoom}) translate(${pan.x / zoom}px, ${pan.y / zoom}px)`,
                            transformOrigin: 'center',
                            transition: isPanning ? 'none' : 'transform 0.2s ease'
                        }}
                    >
                        {/* Floor Plan Background */}
                        <rect x="50" y="50" width="900" height="600" fill="white" stroke="#cbd5e1" strokeWidth="2" />

                        {/* Building Zones */}
                        {/* Main Entrance */}
                        <rect x="80" y="80" width="200" height="150" fill="#e0f2fe" stroke="#0284c7" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="180" y="145" textAnchor="middle" fill="#0284c7" fontSize="14" fontWeight="bold">Main Entrance</text>

                        {/* Loading Dock */}
                        <rect x="720" y="80" width="200" height="150" fill="#fef3c7" stroke="#ca8a04" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="820" y="145" textAnchor="middle" fill="#ca8a04" fontSize="14" fontWeight="bold">Loading Dock</text>

                        {/* Office Area */}
                        <rect x="80" y="280" width="300" height="200" fill="#f3e8ff" stroke="#7c3aed" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="230" y="370" textAnchor="middle" fill="#7c3aed" fontSize="14" fontWeight="bold">Office Area</text>

                        {/* Production Floor */}
                        <rect x="420" y="150" width="300" height="250" fill="#fee2e2" stroke="#dc2626" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="570" y="265" textAnchor="middle" fill="#dc2626" fontSize="14" fontWeight="bold">Production Floor</text>

                        {/* Warehouse */}
                        <rect x="620" y="280" width="300" height="200" fill="#dbeafe" stroke="#2563eb" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="770" y="370" textAnchor="middle" fill="#2563eb" fontSize="14" fontWeight="bold">Warehouse</text>

                        {/* Cafeteria */}
                        <rect x="420" y="450" width="300" height="180" fill="#dcfce7" stroke="#16a34a" strokeWidth="1" strokeDasharray="5,5" />
                        <text x="570" y="530" textAnchor="middle" fill="#16a34a" fontSize="14" fontWeight="bold">Cafeteria</text>

                        {/* Device Markers */}
                        <TooltipProvider>
                            {devices.map((device) => {
                                const position = getDevicePosition(device.id);
                                if (!position) return null;

                                const x = 50 + (position.x / 100) * 900;
                                const y = 50 + (position.y / 100) * 600;

                                return (
                                    <g 
                                        key={device.id}
                                        style={{ cursor: 'pointer' }}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            if (onDeviceClick) {
                                                onDeviceClick(device.id);
                                            }
                                        }}
                                    >
                                        {/* Device Marker with Glow */}
                                        <circle
                                            cx={x}
                                            cy={y}
                                            r="20"
                                            className={cn(getStatusColor(device.status), getStatusGlow(device.status))}
                                            strokeWidth="3"
                                        />

                                        {/* Device Icon */}
                                        <circle
                                            cx={x}
                                            cy={y}
                                            r="12"
                                            fill="white"
                                        />
                                        
                                        {/* Pulse animation for online devices */}
                                        {device.status === 'online' && (
                                            <circle
                                                cx={x}
                                                cy={y}
                                                r="20"
                                                fill="none"
                                                stroke="#22c55e"
                                                strokeWidth="2"
                                                opacity="0"
                                            >
                                                <animate
                                                    attributeName="r"
                                                    from="20"
                                                    to="30"
                                                    dur="2s"
                                                    repeatCount="indefinite"
                                                />
                                                <animate
                                                    attributeName="opacity"
                                                    from="0.8"
                                                    to="0"
                                                    dur="2s"
                                                    repeatCount="indefinite"
                                                />
                                            </circle>
                                        )}

                                        {/* Device Label */}
                                        <text
                                            x={x}
                                            y={y + 35}
                                            textAnchor="middle"
                                            fill="#1e293b"
                                            fontSize="12"
                                            fontWeight="600"
                                        >
                                            {device.id}
                                        </text>

                                        {/* Status emoji above marker */}
                                        <text
                                            x={x}
                                            y={y - 25}
                                            textAnchor="middle"
                                            fontSize="16"
                                        >
                                            {device.status === 'online' ? '🟢' : 
                                             device.status === 'idle' ? '🟡' : 
                                             device.status === 'offline' ? '🔴' : '🔧'}
                                        </text>
                                    </g>
                                );
                            })}
                        </TooltipProvider>
                    </svg>

                    {/* Instructions Overlay */}
                    <div className="absolute bottom-4 right-4 bg-white/90 backdrop-blur-sm rounded-lg p-3 shadow-lg border border-slate-300">
                        <div className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Info className="h-4 w-4 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="font-medium text-slate-900 mb-1">Map Controls:</p>
                                <ul className="space-y-0.5">
                                    <li>• Drag to pan around the floor plan</li>
                                    <li>• Use zoom buttons to zoom in/out</li>
                                    <li>• Click device markers for details</li>
                                    <li>• Reset button returns to default view</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Device Stats */}
                <div className="grid grid-cols-2 md:grid-cols-6 gap-3 mt-4">
                    {devices.map((device) => {
                        const position = getDevicePosition(device.id);
                        if (!position) return null;

                        return (
                            <div 
                                key={device.id} 
                                className="p-3 bg-slate-50 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors cursor-pointer"
                                onClick={() => onDeviceClick && onDeviceClick(device.id)}
                            >
                                <div className="flex items-center gap-2 mb-1">
                                    <div className={cn(
                                        'w-2 h-2 rounded-full',
                                        device.status === 'online' ? 'bg-green-500' :
                                        device.status === 'idle' ? 'bg-yellow-500' :
                                        device.status === 'offline' ? 'bg-red-500' :
                                        'bg-blue-500'
                                    )}></div>
                                    <span className="text-xs font-semibold text-slate-900">{device.id}</span>
                                </div>
                                <div className="text-xs text-muted-foreground truncate">{device.location}</div>
                                <div className="mt-2 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Scans:</span>
                                    <span className="font-semibold">{device.scansToday}</span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

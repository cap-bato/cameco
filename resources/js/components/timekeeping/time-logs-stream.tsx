import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Clock, MapPin, Hash, Lock, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { EventType } from '@/types/timekeeping-pages';
import { useEffect, useRef, useState } from 'react';
import { EventDetailModal } from './event-detail-modal';
import type { EventDetailData } from './event-detail-modal';

/**
 * Time Log Entry Interface
 * Represents a single RFID tap event from the ledger
 */
interface TimeLogEntry {
    id: number;
    sequenceId: number;
    employeeId: string;
    employeeName: string;
    employeePhoto?: string;
    rfidCard: string;
    eventType: EventType;
    timestamp: string;
    deviceId: string;
    deviceLocation: string;
    verified: boolean;
    hashChain?: string;
    latencyMs?: number;
}

interface TimeLogsStreamProps {
    logs?: TimeLogEntry[];
    maxHeight?: string;
    showLiveIndicator?: boolean;
    className?: string;
    autoScroll?: boolean;
}

/**
 * Mock data for time logs stream
 * Simulates 50+ RFID tap events for demonstration
 */
const mockTimeLogs: TimeLogEntry[] = [
    {
        id: 1,
        sequenceId: 12345,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "time_in",
        timestamp: "2026-01-29T08:05:23",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "a3f2b9c8d1e4f7a2b5c8d9e6f3a0b7c4",
        latencyMs: 125
    },
    {
        id: 2,
        sequenceId: 12346,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "time_in",
        timestamp: "2026-01-29T08:08:15",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "b4c3d2e5f8a1b4c7d0e3f6a9b2c5d8e1",
        latencyMs: 98
    },
    {
        id: 3,
        sequenceId: 12347,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        employeePhoto: "/avatars/pedro.jpg",
        rfidCard: "****-9012",
        eventType: "time_in",
        timestamp: "2026-01-29T08:12:45",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "c5d4e3f6a9b2c5d8e1f4a7b0c3d6e9f2",
        latencyMs: 87
    },
    {
        id: 4,
        sequenceId: 12348,
        employeeId: "EMP-2024-004",
        employeeName: "Ana Lopez",
        employeePhoto: "/avatars/ana.jpg",
        rfidCard: "****-3456",
        eventType: "time_in",
        timestamp: "2026-01-29T08:15:30",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "d6e5f4a7b0c3d6e9f2a5b8c1d4e7f0a3",
        latencyMs: 112
    },
    {
        id: 5,
        sequenceId: 12349,
        employeeId: "EMP-2024-005",
        employeeName: "Jose Garcia",
        employeePhoto: "/avatars/jose.jpg",
        rfidCard: "****-7890",
        eventType: "time_in",
        timestamp: "2026-01-29T08:20:18",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "e7f6a5b8c1d4e7f0a3b6c9d2e5f8a1b4",
        latencyMs: 145
    },
    {
        id: 6,
        sequenceId: 12350,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "break_start",
        timestamp: "2026-01-29T10:15:42",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f8a7b6c9d2e5f8a1b4c7d0e3f6a9b2c5",
        latencyMs: 102
    },
    {
        id: 7,
        sequenceId: 12351,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "break_start",
        timestamp: "2026-01-29T10:18:25",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a9b8c7d0e3f6a9b2c5d8e1f4a7b0c3d6",
        latencyMs: 95
    },
    {
        id: 8,
        sequenceId: 12352,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "break_end",
        timestamp: "2026-01-29T10:30:15",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "b0c9d8e1f4a7b0c3d6e9f2a5b8c1d4e7",
        latencyMs: 88
    },
    {
        id: 9,
        sequenceId: 12353,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "break_end",
        timestamp: "2026-01-29T10:33:08",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "c1d0e9f2a5b8c1d4e7f0a3b6c9d2e5f8",
        latencyMs: 110
    },
    {
        id: 10,
        sequenceId: 12354,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        employeePhoto: "/avatars/pedro.jpg",
        rfidCard: "****-9012",
        eventType: "break_start",
        timestamp: "2026-01-29T12:00:30",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "d2e1f0a3b6c9d2e5f8a1b4c7d0e3f6a9",
        latencyMs: 92
    },
    {
        id: 11,
        sequenceId: 12355,
        employeeId: "EMP-2024-004",
        employeeName: "Ana Lopez",
        employeePhoto: "/avatars/ana.jpg",
        rfidCard: "****-3456",
        eventType: "break_start",
        timestamp: "2026-01-29T12:05:45",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "e3f2a1b4c7d0e3f6a9b2c5d8e1f4a7b0",
        latencyMs: 105
    },
    {
        id: 12,
        sequenceId: 12356,
        employeeId: "EMP-2024-005",
        employeeName: "Jose Garcia",
        employeePhoto: "/avatars/jose.jpg",
        rfidCard: "****-7890",
        eventType: "break_start",
        timestamp: "2026-01-29T12:10:22",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f4a3b2c5d8e1f4a7b0c3d6e9f2a5b8c1",
        latencyMs: 118
    },
    {
        id: 13,
        sequenceId: 12357,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        employeePhoto: "/avatars/pedro.jpg",
        rfidCard: "****-9012",
        eventType: "break_end",
        timestamp: "2026-01-29T12:30:12",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a5b4c3d6e9f2a5b8c1d4e7f0a3b6c9d2",
        latencyMs: 97
    },
    {
        id: 14,
        sequenceId: 12358,
        employeeId: "EMP-2024-004",
        employeeName: "Ana Lopez",
        employeePhoto: "/avatars/ana.jpg",
        rfidCard: "****-3456",
        eventType: "break_end",
        timestamp: "2026-01-29T12:35:28",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "b6c5d4e7f0a3b6c9d2e5f8a1b4c7d0e3",
        latencyMs: 103
    },
    {
        id: 15,
        sequenceId: 12359,
        employeeId: "EMP-2024-005",
        employeeName: "Jose Garcia",
        employeePhoto: "/avatars/jose.jpg",
        rfidCard: "****-7890",
        eventType: "break_end",
        timestamp: "2026-01-29T12:40:55",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "c7d6e5f8a1b4c7d0e3f6a9b2c5d8e1f4",
        latencyMs: 89
    },
    {
        id: 16,
        sequenceId: 12360,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "break_start",
        timestamp: "2026-01-29T15:00:18",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "d8e7f6a9b2c5d8e1f4a7b0c3d6e9f2a5",
        latencyMs: 115
    },
    {
        id: 17,
        sequenceId: 12361,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "break_start",
        timestamp: "2026-01-29T15:05:32",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "e9f8a7b0c3d6e9f2a5b8c1d4e7f0a3b6",
        latencyMs: 122
    },
    {
        id: 18,
        sequenceId: 12362,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "break_end",
        timestamp: "2026-01-29T15:15:45",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f0a9b8c1d4e7f0a3b6c9d2e5f8a1b4c7",
        latencyMs: 94
    },
    {
        id: 19,
        sequenceId: 12363,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "break_end",
        timestamp: "2026-01-29T15:20:12",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a1b0c9d2e5f8a1b4c7d0e3f6a9b2c5d8",
        latencyMs: 108
    },
    {
        id: 20,
        sequenceId: 12364,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        employeePhoto: "/avatars/juan.jpg",
        rfidCard: "****-1234",
        eventType: "time_out",
        timestamp: "2026-01-29T17:30:25",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "b2c1d0e3f6a9b2c5d8e1f4a7b0c3d6e9",
        latencyMs: 101
    },
    {
        id: 21,
        sequenceId: 12365,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        employeePhoto: "/avatars/maria.jpg",
        rfidCard: "****-5678",
        eventType: "time_out",
        timestamp: "2026-01-29T17:35:48",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "c3d2e1f4a7b0c3d6e9f2a5b8c1d4e7f0",
        latencyMs: 96
    },
    {
        id: 22,
        sequenceId: 12366,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        employeePhoto: "/avatars/pedro.jpg",
        rfidCard: "****-9012",
        eventType: "time_out",
        timestamp: "2026-01-29T17:40:15",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "d4e3f2a5b8c1d4e7f0a3b6c9d2e5f8a1",
        latencyMs: 112
    },
    {
        id: 23,
        sequenceId: 12367,
        employeeId: "EMP-2024-004",
        employeeName: "Ana Lopez",
        employeePhoto: "/avatars/ana.jpg",
        rfidCard: "****-3456",
        eventType: "time_out",
        timestamp: "2026-01-29T17:45:32",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "e5f4a3b6c9d2e5f8a1b4c7d0e3f6a9b2",
        latencyMs: 88
    },
    {
        id: 24,
        sequenceId: 12368,
        employeeId: "EMP-2024-005",
        employeeName: "Jose Garcia",
        employeePhoto: "/avatars/jose.jpg",
        rfidCard: "****-7890",
        eventType: "time_out",
        timestamp: "2026-01-29T17:50:20",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "f6a5b4c7d0e3f6a9b2c5d8e1f4a7b0c3",
        latencyMs: 119
    },
    // Additional entries for more variety
    {
        id: 25,
        sequenceId: 12369,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "time_in",
        timestamp: "2026-01-29T08:25:10",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "a7b6c5d8e1f4a7b0c3d6e9f2a5b8c1d4",
        latencyMs: 107
    },
    {
        id: 26,
        sequenceId: 12370,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "time_in",
        timestamp: "2026-01-29T08:28:45",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "b8c7d6e9f2a5b8c1d4e7f0a3b6c9d2e5",
        latencyMs: 93
    },
    {
        id: 27,
        sequenceId: 12371,
        employeeId: "EMP-2024-008",
        employeeName: "Elena Rodriguez",
        rfidCard: "****-0123",
        eventType: "time_in",
        timestamp: "2026-01-29T08:32:18",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: false,
        hashChain: "c9d8e7f0a3b6c9d2e5f8a1b4c7d0e3f6",
        latencyMs: 250
    },
    {
        id: 28,
        sequenceId: 12372,
        employeeId: "EMP-2024-009",
        employeeName: "Miguel Torres",
        rfidCard: "****-4567",
        eventType: "time_in",
        timestamp: "2026-01-29T08:35:55",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "d0e9f8a1b4c7d0e3f6a9b2c5d8e1f4a7",
        latencyMs: 99
    },
    {
        id: 29,
        sequenceId: 12373,
        employeeId: "EMP-2024-010",
        employeeName: "Sofia Morales",
        rfidCard: "****-8901",
        eventType: "time_in",
        timestamp: "2026-01-29T08:40:32",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "e1f0a9b2c5d8e1f4a7b0c3d6e9f2a5b8",
        latencyMs: 111
    },
    {
        id: 30,
        sequenceId: 12374,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "break_start",
        timestamp: "2026-01-29T10:22:15",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f2a1b0c3d6e9f2a5b8c1d4e7f0a3b6c9",
        latencyMs: 104
    },
    {
        id: 31,
        sequenceId: 12375,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "break_start",
        timestamp: "2026-01-29T10:25:40",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a3b2c1d4e7f0a3b6c9d2e5f8a1b4c7d0",
        latencyMs: 91
    },
    {
        id: 32,
        sequenceId: 12376,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "break_end",
        timestamp: "2026-01-29T10:37:22",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "b4c3d2e5f8a1b4c7d0e3f6a9b2c5d8e1",
        latencyMs: 113
    },
    {
        id: 33,
        sequenceId: 12377,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "break_end",
        timestamp: "2026-01-29T10:40:55",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "c5d4e3f6a9b2c5d8e1f4a7b0c3d6e9f2",
        latencyMs: 86
    },
    {
        id: 34,
        sequenceId: 12378,
        employeeId: "EMP-2024-008",
        employeeName: "Elena Rodriguez",
        rfidCard: "****-0123",
        eventType: "break_start",
        timestamp: "2026-01-29T12:15:30",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "d6e5f4a7b0c3d6e9f2a5b8c1d4e7f0a3",
        latencyMs: 98
    },
    {
        id: 35,
        sequenceId: 12379,
        employeeId: "EMP-2024-009",
        employeeName: "Miguel Torres",
        rfidCard: "****-4567",
        eventType: "break_start",
        timestamp: "2026-01-29T12:18:45",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "e7f6a5b8c1d4e7f0a3b6c9d2e5f8a1b4",
        latencyMs: 116
    },
    {
        id: 36,
        sequenceId: 12380,
        employeeId: "EMP-2024-010",
        employeeName: "Sofia Morales",
        rfidCard: "****-8901",
        eventType: "break_start",
        timestamp: "2026-01-29T12:20:12",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f8a7b6c9d2e5f8a1b4c7d0e3f6a9b2c5",
        latencyMs: 100
    },
    {
        id: 37,
        sequenceId: 12381,
        employeeId: "EMP-2024-008",
        employeeName: "Elena Rodriguez",
        rfidCard: "****-0123",
        eventType: "break_end",
        timestamp: "2026-01-29T12:45:18",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a9b8c7d0e3f6a9b2c5d8e1f4a7b0c3d6",
        latencyMs: 95
    },
    {
        id: 38,
        sequenceId: 12382,
        employeeId: "EMP-2024-009",
        employeeName: "Miguel Torres",
        rfidCard: "****-4567",
        eventType: "break_end",
        timestamp: "2026-01-29T12:48:32",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "b0c9d8e1f4a7b0c3d6e9f2a5b8c1d4e7",
        latencyMs: 109
    },
    {
        id: 39,
        sequenceId: 12383,
        employeeId: "EMP-2024-010",
        employeeName: "Sofia Morales",
        rfidCard: "****-8901",
        eventType: "break_end",
        timestamp: "2026-01-29T12:50:45",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "c1d0e9f2a5b8c1d4e7f0a3b6c9d2e5f8",
        latencyMs: 102
    },
    {
        id: 40,
        sequenceId: 12384,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "break_start",
        timestamp: "2026-01-29T15:10:25",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "d2e1f0a3b6c9d2e5f8a1b4c7d0e3f6a9",
        latencyMs: 120
    },
    {
        id: 41,
        sequenceId: 12385,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "break_start",
        timestamp: "2026-01-29T15:12:50",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "e3f2a1b4c7d0e3f6a9b2c5d8e1f4a7b0",
        latencyMs: 88
    },
    {
        id: 42,
        sequenceId: 12386,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "break_end",
        timestamp: "2026-01-29T15:25:15",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "f4a3b2c5d8e1f4a7b0c3d6e9f2a5b8c1",
        latencyMs: 114
    },
    {
        id: 43,
        sequenceId: 12387,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "break_end",
        timestamp: "2026-01-29T15:27:40",
        deviceId: "CAFETERIA-01",
        deviceLocation: "Cafeteria - Break Scanner",
        verified: true,
        hashChain: "a5b4c3d6e9f2a5b8c1d4e7f0a3b6c9d2",
        latencyMs: 92
    },
    {
        id: 44,
        sequenceId: 12388,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "time_out",
        timestamp: "2026-01-29T17:28:35",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "b6c5d4e7f0a3b6c9d2e5f8a1b4c7d0e3",
        latencyMs: 105
    },
    {
        id: 45,
        sequenceId: 12389,
        employeeId: "EMP-2024-007",
        employeeName: "Carlos Fernandez",
        rfidCard: "****-6789",
        eventType: "time_out",
        timestamp: "2026-01-29T17:32:48",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "c7d6e5f8a1b4c7d0e3f6a9b2c5d8e1f4",
        latencyMs: 97
    },
    {
        id: 46,
        sequenceId: 12390,
        employeeId: "EMP-2024-008",
        employeeName: "Elena Rodriguez",
        rfidCard: "****-0123",
        eventType: "time_out",
        timestamp: "2026-01-29T17:38:22",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "d8e7f6a9b2c5d8e1f4a7b0c3d6e9f2a5",
        latencyMs: 110
    },
    {
        id: 47,
        sequenceId: 12391,
        employeeId: "EMP-2024-009",
        employeeName: "Miguel Torres",
        rfidCard: "****-4567",
        eventType: "time_out",
        timestamp: "2026-01-29T17:42:55",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "e9f8a7b0c3d6e9f2a5b8c1d4e7f0a3b6",
        latencyMs: 89
    },
    {
        id: 48,
        sequenceId: 12392,
        employeeId: "EMP-2024-010",
        employeeName: "Sofia Morales",
        rfidCard: "****-8901",
        eventType: "time_out",
        timestamp: "2026-01-29T17:48:10",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "f0a9b8c1d4e7f0a3b6c9d2e5f8a1b4c7",
        latencyMs: 123
    },
    // Late afternoon/overtime entries
    {
        id: 49,
        sequenceId: 12393,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        rfidCard: "****-1234",
        eventType: "overtime_start",
        timestamp: "2026-01-29T18:00:15",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "a1b0c9d2e5f8a1b4c7d0e3f6a9b2c5d8",
        latencyMs: 107
    },
    {
        id: 50,
        sequenceId: 12394,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        rfidCard: "****-9012",
        eventType: "overtime_start",
        timestamp: "2026-01-29T18:02:30",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "b2c1d0e3f6a9b2c5d8e1f4a7b0c3d6e9",
        latencyMs: 94
    },
    {
        id: 51,
        sequenceId: 12395,
        employeeId: "EMP-2024-001",
        employeeName: "Juan Dela Cruz",
        rfidCard: "****-1234",
        eventType: "overtime_end",
        timestamp: "2026-01-29T20:15:45",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "c3d2e1f4a7b0c3d6e9f2a5b8c1d4e7f0",
        latencyMs: 115
    },
    {
        id: 52,
        sequenceId: 12396,
        employeeId: "EMP-2024-003",
        employeeName: "Pedro Reyes",
        rfidCard: "****-9012",
        eventType: "overtime_end",
        timestamp: "2026-01-29T20:18:20",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "d4e3f2a5b8c1d4e7f0a3b6c9d2e5f8a1",
        latencyMs: 101
    },
    // Early morning shift (next day preview)
    {
        id: 53,
        sequenceId: 12397,
        employeeId: "EMP-2024-002",
        employeeName: "Maria Santos",
        rfidCard: "****-5678",
        eventType: "time_in",
        timestamp: "2026-01-30T06:00:12",
        deviceId: "GATE-02",
        deviceLocation: "Gate 2 - Loading Dock",
        verified: true,
        hashChain: "e5f4a3b6c9d2e5f8a1b4c7d0e3f6a9b2",
        latencyMs: 118
    },
    {
        id: 54,
        sequenceId: 12398,
        employeeId: "EMP-2024-004",
        employeeName: "Ana Lopez",
        rfidCard: "****-3456",
        eventType: "time_in",
        timestamp: "2026-01-30T06:05:45",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: true,
        hashChain: "f6a5b4c7d0e3f6a9b2c5d8e1f4a7b0c3",
        latencyMs: 96
    },
    {
        id: 55,
        sequenceId: 12399,
        employeeId: "EMP-2024-006",
        employeeName: "Rosa Martinez",
        rfidCard: "****-2345",
        eventType: "time_in",
        timestamp: "2026-01-30T06:10:28",
        deviceId: "GATE-01",
        deviceLocation: "Gate 1 - Main Entrance",
        verified: false,
        hashChain: "a7b6c5d8e1f4a7b0c3d6e9f2a5b8c1d4",
        latencyMs: 245
    },
];

/**
 * Get event type configuration (icon, colors, labels)
 * Color coding: green (time in), red (time out), amber (breaks), purple/indigo (overtime)
 */
const getEventTypeConfig = (eventType: EventType) => {
    const configs = {
        time_in: {
            emoji: '🟢',
            label: 'Time In',
            color: 'text-green-700',
            bgColor: 'bg-green-50',
            borderColor: 'border-green-300',
            badgeBg: 'bg-green-100',
        },
        time_out: {
            emoji: '🔴',
            label: 'Time Out',
            color: 'text-red-700',
            bgColor: 'bg-red-50',
            borderColor: 'border-red-300',
            badgeBg: 'bg-red-100',
        },
        break_start: {
            emoji: '☕',
            label: 'Break Start',
            color: 'text-amber-700',
            bgColor: 'bg-amber-50',
            borderColor: 'border-amber-300',
            badgeBg: 'bg-amber-100',
        },
        break_end: {
            emoji: '▶️',
            label: 'Break End',
            color: 'text-amber-700',
            bgColor: 'bg-amber-50',
            borderColor: 'border-amber-300',
            badgeBg: 'bg-amber-100',
        },
        overtime_start: {
            emoji: '⏰',
            label: 'OT Start',
            color: 'text-purple-700',
            bgColor: 'bg-purple-50',
            borderColor: 'border-purple-300',
            badgeBg: 'bg-purple-100',
        },
        overtime_end: {
            emoji: '✅',
            label: 'OT End',
            color: 'text-indigo-700',
            bgColor: 'bg-indigo-50',
            borderColor: 'border-indigo-300',
            badgeBg: 'bg-indigo-100',
        },
    };

    return configs[eventType] || configs.time_in;
};

/**
 * Format timestamp to readable time
 */
const formatTime = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
};

/**
 * Format full timestamp with date and time
 */
const formatFullTimestamp = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleString('en-US', { 
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    });
};

/**
 * Get employee initials from name
 */
const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

/**
 * Time Logs Stream Component
 * Displays chronological list of RFID tap events with real-time appearance
 * Features auto-scroll animation for new entries appearing at top
 * 
 * @component
 * @example
 * <TimeLogsStream 
 *   logs={timeLogData}
 *   showLiveIndicator={true}
 *   autoScroll={true}
 *   maxHeight="600px"
 * />
 */
export function TimeLogsStream({ 
    logs = mockTimeLogs,
    maxHeight = '600px',
    showLiveIndicator = true,
    autoScroll = true,
    className 
}: TimeLogsStreamProps) {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [newEntryIndices, setNewEntryIndices] = useState<Set<number>>(new Set());
    const previousLogsLength = useRef(logs.length);
    const [selectedEvent, setSelectedEvent] = useState<EventDetailData | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    // Handle log entry click
    const handleLogClick = (log: TimeLogEntry) => {
        // Enhance log data with additional mock details for the modal
        const enhancedLog: EventDetailData = {
            ...log,
            employeeDepartment: 'Manufacturing',
            employeePosition: 'Production Staff',
            deviceStatus: 'online',
            deviceLastMaintenance: '2026-01-28T14:30:00',
            verificationStatus: log.verified ? 'verified' : 'pending',
            processedAt: new Date(new Date(log.timestamp).getTime() + (log.latencyMs || 100)).toISOString(),
            summaryImpact: 'Added to daily attendance summary',
            signature: 'ed25519:' + (log.hashChain?.substring(0, 32) || '0'.repeat(32))
        };
        setSelectedEvent(enhancedLog);
        setIsModalOpen(true);
    };

    // Enhance all logs for modal navigation
    const enhancedLogs: EventDetailData[] = logs.map(log => ({
        ...log,
        employeeDepartment: 'Manufacturing',
        employeePosition: 'Production Staff',
        deviceStatus: 'online' as 'online' | 'offline' | 'maintenance',
        deviceLastMaintenance: '2026-01-28T14:30:00',
        verificationStatus: log.verified ? 'verified' as 'verified' | 'pending' | 'failed' : 'pending' as 'verified' | 'pending' | 'failed',
        processedAt: new Date(new Date(log.timestamp).getTime() + (log.latencyMs || 100)).toISOString(),
        summaryImpact: 'Added to daily attendance summary',
        signature: 'ed25519:' + (log.hashChain?.substring(0, 32) || '0'.repeat(32))
    }));

    // Handle navigation to another event from modal
    const handleNavigate = (targetEvent: EventDetailData) => {
        setSelectedEvent(targetEvent);
    };

    // Auto-scroll to top when new entries are added
    useEffect(() => {
        if (autoScroll && scrollContainerRef.current) {
            const hasNewEntries = logs.length > previousLogsLength.current;
            
            if (hasNewEntries) {
                // Mark new entries for animation
                const newIndices = new Set<number>();
                const newEntriesCount = logs.length - previousLogsLength.current;
                for (let i = 0; i < Math.min(newEntriesCount, 5); i++) {
                    newIndices.add(i);
                }
                
                // Defer setState to avoid synchronous updates in effect
                setTimeout(() => {
                    setNewEntryIndices(newIndices);
                }, 0);

                // Smooth scroll to top
                scrollContainerRef.current.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                // Remove animation markers after animation completes
                setTimeout(() => {
                    setNewEntryIndices(new Set());
                }, 500);
            }
            
            previousLogsLength.current = logs.length;
        }
    }, [logs, autoScroll]);

    return (
        <Card className={cn('w-full', className)}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-3">
                            Live Event Stream
                            {showLiveIndicator && (
                                <div className="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-green-50 border border-green-200">
                                    <span className="relative flex h-2 w-2">
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    <span className="text-[10px] font-semibold text-green-700 uppercase tracking-wide">
                                        Live
                                    </span>
                                </div>
                            )}
                        </CardTitle>
                        <CardDescription>
                            Real-time RFID attendance events from ledger
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="text-xs">
                        {logs.length} Events
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div 
                    ref={scrollContainerRef}
                    className="space-y-2 overflow-y-auto pr-2 scroll-smooth"
                    style={{ maxHeight }}
                >
                    {logs.map((log, index) => {
                        const config = getEventTypeConfig(log.eventType);
                        const timeStr = formatTime(log.timestamp);
                        const fullTimestamp = formatFullTimestamp(log.timestamp);
                        const initials = getInitials(log.employeeName);
                        const isNewEntry = newEntryIndices.has(index);

                        return (
                            <Tooltip key={log.id} delayDuration={300}>
                                <TooltipTrigger asChild>
                                    <div
                                        onClick={() => handleLogClick(log)}
                                        className={cn(
                                            'group relative flex items-start gap-3 p-3 rounded-lg border-2 transition-all duration-200',
                                            'hover:shadow-lg hover:scale-[1.02] cursor-pointer',
                                            config.bgColor,
                                            config.borderColor,
                                            // Slide-in animation for new entries appearing at top
                                            isNewEntry && 'animate-in slide-in-from-top-2 fade-in duration-500'
                                        )}
                                    >
                                        {/* Employee Avatar */}
                                        <Avatar className="h-10 w-10 border-2 border-white shadow-sm">
                                            <AvatarImage src={log.employeePhoto} alt={log.employeeName} />
                                            <AvatarFallback className={cn(config.badgeBg, config.color, 'font-semibold')}>
                                                {initials}
                                            </AvatarFallback>
                                        </Avatar>

                                        {/* Event Details */}
                                        <div className="flex-1 min-w-0">
                                            {/* Employee Name & ID */}
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-semibold text-sm text-gray-900 truncate">
                                                    {log.employeeName}
                                                </span>
                                                <span className="text-xs text-gray-500 font-mono">
                                                    {log.employeeId}
                                                </span>
                                            </div>

                                            {/* Event Type Badge & Timestamp */}
                                            <div className="flex items-center gap-2 flex-wrap mb-2">
                                                <Badge 
                                                    variant="secondary"
                                                    className={cn(
                                                        'text-xs font-semibold border',
                                                        config.color,
                                                        config.badgeBg,
                                                        config.borderColor
                                                    )}
                                                >
                                                    <span className="mr-1">{config.emoji}</span>
                                                    {config.label}
                                                </Badge>
                                                <div className="flex items-center gap-1 text-xs text-gray-700 font-medium">
                                                    <Clock className="h-3 w-3" />
                                                    <span>{timeStr}</span>
                                                </div>
                                            </div>

                                            {/* Device Location */}
                                            <div className="flex items-center gap-1 text-xs text-gray-600 mb-1">
                                                <MapPin className="h-3 w-3" />
                                                <span>{log.deviceLocation}</span>
                                            </div>

                                            {/* Sequence ID & Verification Status */}
                                            <div className="flex items-center gap-3 text-xs">
                                                <div className="flex items-center gap-1 text-gray-500">
                                                    <Hash className="h-3 w-3" />
                                                    <span className="font-mono">{log.sequenceId}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    {log.verified ? (
                                                        <>
                                                            <Lock className="h-3 w-3 text-green-600" />
                                                            <span className="text-green-600 font-medium">Verified</span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <AlertTriangle className="h-3 w-3 text-yellow-600" />
                                                            <span className="text-yellow-600 font-medium">Pending</span>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Hover Effect - Show latency badge */}
                                        {log.latencyMs && (
                                            <div className="absolute right-3 top-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <Badge variant="outline" className="text-xs">
                                                    {log.latencyMs}ms
                                                </Badge>
                                            </div>
                                        )}
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent side="left" className="max-w-sm">
                                    <div className="space-y-2 text-xs">
                                        <div className="font-semibold text-sm border-b pb-1 mb-2">
                                            Event Details
                                        </div>
                                        
                                        <div className="grid grid-cols-[80px_1fr] gap-x-2 gap-y-1">
                                            <span className="text-gray-400">Employee:</span>
                                            <span className="font-medium">{log.employeeName}</span>
                                            
                                            <span className="text-gray-400">ID:</span>
                                            <span className="font-mono">{log.employeeId}</span>
                                            
                                            <span className="text-gray-400">RFID Card:</span>
                                            <span className="font-mono">{log.rfidCard}</span>
                                            
                                            <span className="text-gray-400">Event Type:</span>
                                            <span className="font-medium">{config.emoji} {config.label}</span>
                                            
                                            <span className="text-gray-400">Timestamp:</span>
                                            <span className="font-mono">{fullTimestamp}</span>
                                            
                                            <span className="text-gray-400">Device ID:</span>
                                            <span className="font-mono">{log.deviceId}</span>
                                            
                                            <span className="text-gray-400">Location:</span>
                                            <span>{log.deviceLocation}</span>
                                            
                                            <span className="text-gray-400">Sequence:</span>
                                            <span className="font-mono">#{log.sequenceId}</span>
                                            
                                            <span className="text-gray-400">Status:</span>
                                            <span className={log.verified ? 'text-green-400' : 'text-yellow-400'}>
                                                {log.verified ? '🔒 Verified' : '⚠️ Pending Verification'}
                                            </span>
                                            
                                            {log.latencyMs && (
                                                <>
                                                    <span className="text-gray-400">Latency:</span>
                                                    <span>{log.latencyMs}ms</span>
                                                </>
                                            )}
                                            
                                            {log.hashChain && (
                                                <>
                                                    <span className="text-gray-400">Hash:</span>
                                                    <span className="font-mono text-[10px] truncate" title={log.hashChain}>
                                                        {log.hashChain.substring(0, 16)}...
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        );
                    })}

                    {/* Empty State */}
                    {logs.length === 0 && (
                        <div className="text-center py-12 text-gray-500">
                            <Clock className="h-12 w-12 mx-auto mb-3 opacity-50" />
                            <p className="text-sm">No events recorded yet</p>
                            <p className="text-xs mt-1">Waiting for RFID taps...</p>
                        </div>
                    )}
                </div>
            </CardContent>

            {/* Event Detail Modal */}
            <EventDetailModal
                open={isModalOpen}
                onOpenChange={setIsModalOpen}
                event={selectedEvent}
                allLogs={enhancedLogs}
                onNavigate={handleNavigate}
            />
        </Card>
    );
}

// Export mock data for use in other components
export { mockTimeLogs };

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { 
    Select, 
    SelectContent, 
    SelectItem, 
    SelectTrigger, 
    SelectValue 
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Filter, 
    X, 
    ChevronDown, 
    ChevronUp, 
    Search, 
    Calendar,
    Building2,
    MapPin,
    AlertTriangle,
    Clock,
    Save,
    Upload
} from 'lucide-react';
import { EventType } from '@/types/timekeeping-pages';
import { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Filter Preset Interface
 */
interface FilterPreset {
    name: string;
    filters: LogsFilterConfig;
    createdAt: string;
}

/**
 * Filter Configuration Interface
 */
export interface LogsFilterConfig {
    // Basic Filters
    dateRange: 'today' | 'yesterday' | 'this_week' | 'custom';
    customDateFrom?: string;
    customDateTo?: string;
    department: string; // 'all' or specific department
    eventTypes: EventType[];
    verificationStatus: 'all' | 'verified' | 'pending' | 'failed';
    deviceLocations: string[]; // Array of device IDs or 'all'
    employeeSearch: string;
    
    // Advanced Filters
    sequenceRangeFrom?: number;
    sequenceRangeTo?: number;
    latencyThreshold?: number; // milliseconds
    violationType?: 'all' | 'late_arrival' | 'early_departure' | 'missing_punch' | 'extended_break';
}

interface LogsFilterPanelProps {
    filters: LogsFilterConfig;
    onFiltersChange: (filters: LogsFilterConfig) => void;
    onClearFilters: () => void;
    className?: string;
}

/**
 * Mock Department Options
 */
const mockDepartments = [
    { value: 'all', label: 'All Departments' },
    { value: 'production', label: 'Production' },
    { value: 'admin', label: 'Administration' },
    { value: 'sales', label: 'Sales & Marketing' },
    { value: 'warehouse', label: 'Warehouse' },
    { value: 'quality', label: 'Quality Control' },
    { value: 'maintenance', label: 'Maintenance' }
];

/**
 * Mock Device Locations
 */
const mockDeviceLocations = [
    { id: 'GATE-01', label: 'Gate 1 - Main Entrance' },
    { id: 'GATE-02', label: 'Gate 2 - Loading Dock' },
    { id: 'CAFETERIA-01', label: 'Cafeteria - Break Scanner' },
    { id: 'OFFICE-01', label: 'Office Building Entrance' },
    { id: 'WAREHOUSE-01', label: 'Warehouse Entry' }
];

/**
 * Event Type Options
 */
const eventTypeOptions: { value: EventType; label: string; emoji: string }[] = [
    { value: 'time_in', label: 'Time In', emoji: '🟢' },
    { value: 'time_out', label: 'Time Out', emoji: '🔴' },
    { value: 'break_start', label: 'Break Start', emoji: '☕' },
    { value: 'break_end', label: 'Break End', emoji: '▶️' },
    { value: 'overtime_start', label: 'Overtime Start', emoji: '⏰' },
    { value: 'overtime_end', label: 'Overtime End', emoji: '✅' }
];

/**
 * Violation Type Options
 */
const violationTypeOptions = [
    { value: 'all', label: 'All Violations' },
    { value: 'late_arrival', label: 'Late Arrival' },
    { value: 'early_departure', label: 'Early Departure' },
    { value: 'missing_punch', label: 'Missing Punch' },
    { value: 'extended_break', label: 'Extended Break' }
];

/**
 * Date Range Options
 */
const dateRangeOptions = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'custom', label: 'Custom Range' }
];

/**
 * Logs Filter Panel Component
 * Comprehensive filter interface for time logs stream
 */
export function LogsFilterPanel({
    filters,
    onFiltersChange,
    onClearFilters,
    className
}: LogsFilterPanelProps) {
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const [activeFilterCount, setActiveFilterCount] = useState(0);
    const [savedPresets, setSavedPresets] = useState<FilterPreset[]>([]);
    const [showSaveDialog, setShowSaveDialog] = useState(false);
    const [showLoadDialog, setShowLoadDialog] = useState(false);
    const [presetName, setPresetName] = useState('');

    // Load saved presets from localStorage on mount
    useEffect(() => {
        try {
            const stored = localStorage.getItem('timekeeping-filter-presets');
            if (stored) {
                setSavedPresets(JSON.parse(stored));
            }
        } catch (error) {
            console.error('Failed to load filter presets:', error);
        }
    }, []);

    // Calculate active filter count
    useEffect(() => {
        let count = 0;
        
        if (filters.dateRange !== 'today') count++;
        if (filters.department !== 'all') count++;
        if (filters.eventTypes.length < 6) count++; // Not all event types selected
        if (filters.verificationStatus !== 'all') count++;
        if (filters.deviceLocations.length > 0 && !filters.deviceLocations.includes('all')) count++;
        if (filters.employeeSearch) count++;
        if (filters.sequenceRangeFrom || filters.sequenceRangeTo) count++;
        if (filters.latencyThreshold) count++;
        if (filters.violationType && filters.violationType !== 'all') count++;
        
        setActiveFilterCount(count);
    }, [filters]);

    // Helper functions for filter updates
    const updateFilter = <K extends keyof LogsFilterConfig>(key: K, value: LogsFilterConfig[K]) => {
        onFiltersChange({ ...filters, [key]: value });
    };

    const toggleEventType = (eventType: EventType) => {
        const newEventTypes = filters.eventTypes.includes(eventType)
            ? filters.eventTypes.filter(t => t !== eventType)
            : [...filters.eventTypes, eventType];
        updateFilter('eventTypes', newEventTypes);
    };

    const toggleDeviceLocation = (deviceId: string) => {
        if (deviceId === 'all') {
            updateFilter('deviceLocations', filters.deviceLocations.includes('all') ? [] : ['all']);
        } else {
            const newLocations = filters.deviceLocations.includes(deviceId)
                ? filters.deviceLocations.filter(id => id !== deviceId && id !== 'all')
                : [...filters.deviceLocations.filter(id => id !== 'all'), deviceId];
            updateFilter('deviceLocations', newLocations);
        }
    };

    const removeFilter = (filterType: string) => {
        switch (filterType) {
            case 'dateRange':
                updateFilter('dateRange', 'today');
                updateFilter('customDateFrom', undefined);
                updateFilter('customDateTo', undefined);
                break;
            case 'department':
                updateFilter('department', 'all');
                break;
            case 'eventTypes':
                updateFilter('eventTypes', ['time_in', 'time_out', 'break_start', 'break_end', 'overtime_start', 'overtime_end']);
                break;
            case 'verificationStatus':
                updateFilter('verificationStatus', 'all');
                break;
            case 'deviceLocations':
                updateFilter('deviceLocations', []);
                break;
            case 'employeeSearch':
                updateFilter('employeeSearch', '');
                break;
            case 'sequenceRange':
                updateFilter('sequenceRangeFrom', undefined);
                updateFilter('sequenceRangeTo', undefined);
                break;
            case 'latencyThreshold':
                updateFilter('latencyThreshold', undefined);
                break;
            case 'violationType':
                updateFilter('violationType', 'all');
                break;
        }
    };

    // Get active filter chips
    const getActiveFilterChips = () => {
        const chips: { label: string; type: string }[] = [];
        
        if (filters.dateRange !== 'today') {
            const rangeLabel = dateRangeOptions.find(o => o.value === filters.dateRange)?.label || filters.dateRange;
            chips.push({ label: `Date: ${rangeLabel}`, type: 'dateRange' });
        }
        
        if (filters.department !== 'all') {
            const deptLabel = mockDepartments.find(d => d.value === filters.department)?.label || filters.department;
            chips.push({ label: `Dept: ${deptLabel}`, type: 'department' });
        }
        
        if (filters.eventTypes.length < 6) {
            chips.push({ label: `${filters.eventTypes.length} Event Type(s)`, type: 'eventTypes' });
        }
        
        if (filters.verificationStatus !== 'all') {
            chips.push({ label: `Status: ${filters.verificationStatus}`, type: 'verificationStatus' });
        }
        
        if (filters.deviceLocations.length > 0 && !filters.deviceLocations.includes('all')) {
            chips.push({ label: `${filters.deviceLocations.length} Location(s)`, type: 'deviceLocations' });
        }
        
        if (filters.employeeSearch) {
            chips.push({ label: `Search: ${filters.employeeSearch}`, type: 'employeeSearch' });
        }
        
        if (filters.sequenceRangeFrom || filters.sequenceRangeTo) {
            chips.push({ label: 'Sequence Range', type: 'sequenceRange' });
        }
        
        if (filters.latencyThreshold) {
            chips.push({ label: `Latency > ${filters.latencyThreshold}ms`, type: 'latencyThreshold' });
        }
        
        if (filters.violationType && filters.violationType !== 'all') {
            chips.push({ label: `Violation: ${filters.violationType.replace('_', ' ')}`, type: 'violationType' });
        }
        
        return chips;
    };

    // Save preset to localStorage
    const handleSavePreset = () => {
        if (!presetName.trim()) return;

        const newPreset: FilterPreset = {
            name: presetName.trim(),
            filters: { ...filters },
            createdAt: new Date().toISOString()
        };

        const updatedPresets = [...savedPresets, newPreset];
        
        try {
            localStorage.setItem('timekeeping-filter-presets', JSON.stringify(updatedPresets));
            setSavedPresets(updatedPresets);
            setShowSaveDialog(false);
            setPresetName('');
        } catch (error) {
            console.error('Failed to save filter preset:', error);
            alert('Failed to save preset. Please try again.');
        }
    };

    // Load preset from saved list
    const handleLoadPreset = (preset: FilterPreset) => {
        onFiltersChange(preset.filters);
        setShowLoadDialog(false);
    };

    // Delete preset
    const handleDeletePreset = (index: number) => {
        const updatedPresets = savedPresets.filter((_, i) => i !== index);
        
        try {
            localStorage.setItem('timekeeping-filter-presets', JSON.stringify(updatedPresets));
            setSavedPresets(updatedPresets);
        } catch (error) {
            console.error('Failed to delete filter preset:', error);
        }
    };

    const activeChips = getActiveFilterChips();

    return (
        <Card className={cn('w-full', className)}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <Filter className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                Filters & Controls
                                {activeFilterCount > 0 && (
                                    <Badge variant="secondary" className="ml-2">
                                        {activeFilterCount} active
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                Filter and search time logs
                            </CardDescription>
                        </div>
                    </div>
                    
                    {activeFilterCount > 0 && (
                        <Button 
                            variant="ghost" 
                            size="sm"
                            onClick={onClearFilters}
                            className="text-muted-foreground hover:text-destructive"
                        >
                            <X className="h-4 w-4 mr-2" />
                            Clear All
                        </Button>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                {/* Active Filter Chips */}
                {activeChips.length > 0 && (
                    <div className="flex flex-wrap gap-2 p-3 bg-slate-50 rounded-lg border">
                        <span className="text-xs font-medium text-muted-foreground">Active Filters:</span>
                        {activeChips.map((chip, index) => (
                            <Badge 
                                key={index}
                                variant="secondary"
                                className="flex items-center gap-1.5 pr-1"
                            >
                                {chip.label}
                                <button
                                    onClick={() => removeFilter(chip.type)}
                                    className="ml-1 hover:bg-slate-300 rounded-full p-0.5 transition-colors"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        ))}
                    </div>
                )}

                {/* Basic Filters */}
                <div className="space-y-4">
                    {/* Date Range */}
                    <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            Date Range
                        </Label>
                        <Select 
                            value={filters.dateRange} 
                            onValueChange={(value) => updateFilter('dateRange', value as LogsFilterConfig['dateRange'])}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {dateRangeOptions.map(option => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        
                        {/* Custom Date Range Inputs */}
                        {filters.dateRange === 'custom' && (
                            <div className="grid grid-cols-2 gap-2 mt-2">
                                <div>
                                    <Label className="text-xs">From</Label>
                                    <Input
                                        type="date"
                                        value={filters.customDateFrom || ''}
                                        onChange={(e) => updateFilter('customDateFrom', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">To</Label>
                                    <Input
                                        type="date"
                                        value={filters.customDateTo || ''}
                                        onChange={(e) => updateFilter('customDateTo', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Department */}
                    <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                            <Building2 className="h-4 w-4" />
                            Department
                        </Label>
                        <Select 
                            value={filters.department} 
                            onValueChange={(value) => updateFilter('department', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {mockDepartments.map(dept => (
                                    <SelectItem key={dept.value} value={dept.value}>
                                        {dept.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Event Types */}
                    <div className="space-y-2">
                        <Label>Event Types</Label>
                        <div className="grid grid-cols-2 gap-2">
                            {eventTypeOptions.map(eventType => (
                                <div key={eventType.value} className="flex items-center space-x-2">
                                    <Checkbox
                                        id={`event-${eventType.value}`}
                                        checked={filters.eventTypes.includes(eventType.value)}
                                        onCheckedChange={() => toggleEventType(eventType.value)}
                                    />
                                    <Label 
                                        htmlFor={`event-${eventType.value}`}
                                        className="text-sm font-normal cursor-pointer"
                                    >
                                        <span className="mr-1">{eventType.emoji}</span>
                                        {eventType.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Verification Status */}
                    <div className="space-y-2">
                        <Label>Verification Status</Label>
                        <Select 
                            value={filters.verificationStatus} 
                            onValueChange={(value) => updateFilter('verificationStatus', value as LogsFilterConfig['verificationStatus'])}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="verified">✅ Verified</SelectItem>
                                <SelectItem value="pending">⏳ Pending</SelectItem>
                                <SelectItem value="failed">❌ Failed</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Device Locations */}
                    <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                            <MapPin className="h-4 w-4" />
                            Device Locations
                        </Label>
                        <div className="space-y-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="location-all"
                                    checked={filters.deviceLocations.includes('all')}
                                    onCheckedChange={() => toggleDeviceLocation('all')}
                                />
                                <Label 
                                    htmlFor="location-all"
                                    className="text-sm font-medium cursor-pointer"
                                >
                                    All Locations
                                </Label>
                            </div>
                            <Separator />
                            {mockDeviceLocations.map(location => (
                                <div key={location.id} className="flex items-center space-x-2">
                                    <Checkbox
                                        id={`location-${location.id}`}
                                        checked={filters.deviceLocations.includes(location.id)}
                                        onCheckedChange={() => toggleDeviceLocation(location.id)}
                                        disabled={filters.deviceLocations.includes('all')}
                                    />
                                    <Label 
                                        htmlFor={`location-${location.id}`}
                                        className="text-sm font-normal cursor-pointer"
                                    >
                                        {location.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Employee Search */}
                    <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                            <Search className="h-4 w-4" />
                            Employee Search
                        </Label>
                        <Input
                            type="text"
                            placeholder="Search by name or ID..."
                            value={filters.employeeSearch}
                            onChange={(e) => updateFilter('employeeSearch', e.target.value)}
                        />
                    </div>
                </div>

                {/* Advanced Filters Section */}
                <Collapsible open={advancedOpen} onOpenChange={setAdvancedOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="ghost" className="w-full justify-between">
                            <span className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4" />
                                Advanced Filters
                            </span>
                            {advancedOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    
                    <CollapsibleContent className="space-y-4 mt-4">
                        {/* Sequence Range */}
                        <div className="space-y-2">
                            <Label>Sequence Range</Label>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Input
                                        type="number"
                                        placeholder="From"
                                        value={filters.sequenceRangeFrom || ''}
                                        onChange={(e) => updateFilter('sequenceRangeFrom', e.target.value ? parseInt(e.target.value) : undefined)}
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="number"
                                        placeholder="To"
                                        value={filters.sequenceRangeTo || ''}
                                        onChange={(e) => updateFilter('sequenceRangeTo', e.target.value ? parseInt(e.target.value) : undefined)}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Processing Latency Threshold */}
                        <div className="space-y-2">
                            <Label className="flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Processing Latency (ms)
                            </Label>
                            <Input
                                type="number"
                                placeholder="Show events slower than..."
                                value={filters.latencyThreshold || ''}
                                onChange={(e) => updateFilter('latencyThreshold', e.target.value ? parseInt(e.target.value) : undefined)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Show only events with processing time exceeding this threshold
                            </p>
                        </div>

                        {/* Violation Type */}
                        <div className="space-y-2">
                            <Label className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4" />
                                Violation Type
                            </Label>
                            <Select 
                                value={filters.violationType || 'all'} 
                                onValueChange={(value) => updateFilter('violationType', value as LogsFilterConfig['violationType'])}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {violationTypeOptions.map(option => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <Separator />

                {/* Action Buttons */}
                <div className="flex gap-2">
                    <Button 
                        variant="outline" 
                        className="flex-1"
                        onClick={() => setShowSaveDialog(true)}
                    >
                        <Save className="h-4 w-4 mr-2" />
                        Save Preset
                    </Button>
                    <Button 
                        variant="outline" 
                        className="flex-1"
                        onClick={() => setShowLoadDialog(true)}
                        disabled={savedPresets.length === 0}
                    >
                        <Upload className="h-4 w-4 mr-2" />
                        Load Preset {savedPresets.length > 0 && `(${savedPresets.length})`}
                    </Button>
                </div>
            </CardContent>

            {/* Save Preset Dialog */}
            <Dialog open={showSaveDialog} onOpenChange={setShowSaveDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Save Filter Preset</DialogTitle>
                        <DialogDescription>
                            Give your filter configuration a name to save it for later use.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="preset-name">Preset Name</Label>
                            <Input
                                id="preset-name"
                                placeholder="e.g., Production Late Arrivals"
                                value={presetName}
                                onChange={(e) => setPresetName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && presetName.trim()) {
                                        handleSavePreset();
                                    }
                                }}
                            />
                        </div>
                        <div className="text-sm text-muted-foreground">
                            <p className="font-medium mb-2">Current filters:</p>
                            <ul className="list-disc list-inside space-y-1">
                                {getActiveFilterChips().map((chip, i) => (
                                    <li key={i}>{chip.label}</li>
                                ))}
                                {getActiveFilterChips().length === 0 && (
                                    <li>All default filters (no customization)</li>
                                )}
                            </ul>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowSaveDialog(false)}>
                            Cancel
                        </Button>
                        <Button 
                            onClick={handleSavePreset}
                            disabled={!presetName.trim()}
                        >
                            Save Preset
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Load Preset Dialog */}
            <Dialog open={showLoadDialog} onOpenChange={setShowLoadDialog}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Load Filter Preset</DialogTitle>
                        <DialogDescription>
                            Choose a saved preset to apply its filters.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2 py-4 max-h-96 overflow-y-auto">
                        {savedPresets.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                <p>No saved presets yet.</p>
                                <p className="text-sm mt-2">Configure filters and click "Save Preset" to create one.</p>
                            </div>
                        ) : (
                            savedPresets.map((preset, index) => (
                                <div 
                                    key={index}
                                    className="flex items-center justify-between p-3 border rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    <div className="flex-1">
                                        <p className="font-medium">{preset.name}</p>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            Created: {new Date(preset.createdAt).toLocaleString()}
                                        </p>
                                        <div className="flex flex-wrap gap-1 mt-2">
                                            {preset.filters.dateRange !== 'today' && (
                                                <Badge variant="secondary" className="text-xs">
                                                    Date: {dateRangeOptions.find(o => o.value === preset.filters.dateRange)?.label}
                                                </Badge>
                                            )}
                                            {preset.filters.department !== 'all' && (
                                                <Badge variant="secondary" className="text-xs">
                                                    {mockDepartments.find(d => d.value === preset.filters.department)?.label}
                                                </Badge>
                                            )}
                                            {preset.filters.eventTypes.length < 6 && (
                                                <Badge variant="secondary" className="text-xs">
                                                    {preset.filters.eventTypes.length} event types
                                                </Badge>
                                            )}
                                            {preset.filters.verificationStatus !== 'all' && (
                                                <Badge variant="secondary" className="text-xs">
                                                    Status: {preset.filters.verificationStatus}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex gap-2 ml-4">
                                        <Button
                                            size="sm"
                                            onClick={() => handleLoadPreset(preset)}
                                        >
                                            Load
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => handleDeletePreset(index)}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowLoadDialog(false)}>
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

/**
 * Default filter configuration
 */
export const defaultFilters: LogsFilterConfig = {
    dateRange: 'today',
    department: 'all',
    eventTypes: ['time_in', 'time_out', 'break_start', 'break_end', 'overtime_start', 'overtime_end'],
    verificationStatus: 'all',
    deviceLocations: [],
    employeeSearch: '',
    violationType: 'all'
};

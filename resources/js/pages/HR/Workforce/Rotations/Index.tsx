import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { RotationCard } from '@/components/workforce/rotation-card';
import { RotationFilters } from '@/components/workforce/rotation-filters';
import { CreateEditRotationModal } from './CreateEditRotationModal';
import { AssignEmployeesModal } from './AssignEmployeesModal';
import { RotationDetailsModal } from './RotationDetailsModal';
import { EmployeeRotation, RotationsIndexProps } from '@/types/workforce-pages';
import { Plus, Filter } from 'lucide-react';

export default function RotationsIndex({ rotations, summary, departments, pattern_templates }: RotationsIndexProps) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
    const [editingRotation, setEditingRotation] = useState<EmployeeRotation | null>(null);
    const [selectedRotationForAssignment, setSelectedRotationForAssignment] = useState<EmployeeRotation | null>(null);
    const [selectedRotationForDetails, setSelectedRotationForDetails] = useState<EmployeeRotation | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [filters, setFilters] = useState({
        pattern_type: 'all',
        department_id: 'all',
        status: 'all',
    });

    const [showFilters, setShowFilters] = useState(false);

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Rotations', href: '/hr/workforce/rotations' },
    ];

    // Filter rotations based on search and filters
    const filteredRotations = (Array.isArray(rotations) ? rotations : rotations.data || []).filter((rotation) => {
        const matchesSearch = !searchTerm || 
            rotation.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            rotation.description?.toLowerCase().includes(searchTerm.toLowerCase());

        const matchesPattern = filters.pattern_type === 'all' || rotation.pattern_type === filters.pattern_type;
        const matchesDept = filters.department_id === 'all' || (rotation.department_id != null && rotation.department_id.toString() === filters.department_id);
        const matchesStatus = filters.status === 'all' ||
            (filters.status === 'active' && rotation.is_active) ||
            (filters.status === 'inactive' && !rotation.is_active);

        return matchesSearch && matchesPattern && matchesDept && matchesStatus;
    });

    const handleEdit = (rotation: EmployeeRotation) => {
        setEditingRotation(rotation);
        setIsCreateModalOpen(true);
    };

    const handleDelete = (rotationId: number) => {
        if (window.confirm('Are you sure you want to delete this rotation? This action cannot be undone.')) {
            router.delete(`/hr/workforce/rotations/${rotationId}`, {
                onFinish: () => {
                    // Rotation deleted successfully
                },
            });
        }
    };

    const handleDuplicate = (rotation: EmployeeRotation) => {
        setEditingRotation({
            ...rotation,
            id: 0, // Reset ID for new rotation
            name: `${rotation.name} (Copy)`,
        });
        setIsCreateModalOpen(true);
    };

    const handleAssignEmployees = (rotation: EmployeeRotation) => {
        setSelectedRotationForAssignment(rotation);
        setIsAssignModalOpen(true);
    };

    const handleViewDetails = (rotation: EmployeeRotation) => {
        setSelectedRotationForDetails(rotation);
        setIsDetailsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsCreateModalOpen(false);
        setEditingRotation(null);
    };

    const handleCloseDetailsModal = () => {
        setIsDetailsModalOpen(false);
        setSelectedRotationForDetails(null);
    };

    const handleSaveRotation = (data: Record<string, unknown>) => {
        const payload = {
            ...data,
            pattern_json: typeof data.pattern_json === 'string' 
                ? JSON.parse(data.pattern_json as string) 
                : data.pattern_json,
        };
        
        if (data.id) {
            router.put(`/hr/workforce/rotations/${data.id}`, payload, {
                onFinish: handleCloseModal,
            });
        } else {
            router.post('/hr/workforce/rotations', payload, {
                onFinish: handleCloseModal,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Employee Rotations" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Employee Rotations</h1>
                        <p className="text-gray-600 mt-1">Manage rotation patterns and employee schedules</p>
                    </div>
                    <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Create Rotation
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Rotations</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary?.total_rotations || 0}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Active Patterns</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">{summary?.active_patterns || 0}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Employees in Rotation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-blue-600">{summary?.employees_in_rotation || 0}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Coverage %</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-orange-600">{summary?.coverage_percentage || 0}%</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Search and Filters */}
                <div className="space-y-4">
                    <div className="flex gap-2">
                        <Input
                            placeholder="Search rotations by name or description..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="flex-1"
                        />
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                            className="gap-2"
                        >
                            <Filter className="h-4 w-4" />
                            Filters
                        </Button>
                    </div>

                    {showFilters && (
                        <RotationFilters
                            filters={filters}
                            onFiltersChange={(newFilters) => setFilters(newFilters as typeof filters)}
                            departments={departments}
                        />
                    )}
                </div>

                {/* Results Count */}
                {filteredRotations.length > 0 && (
                    <p className="text-sm text-gray-600">
                        Showing {filteredRotations.length} of {(Array.isArray(rotations) ? rotations : rotations.data || []).length} rotations
                    </p>
                )}

                {/* Rotations Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {filteredRotations.length > 0 ? (
                        filteredRotations.map((rotation) => (
                            <RotationCard
                                key={rotation.id}
                                rotation={rotation}
                                onEdit={handleEdit}
                                onDelete={handleDelete}
                                onDuplicate={handleDuplicate}
                                onAssignEmployees={handleAssignEmployees}
                                onViewDetails={handleViewDetails}
                            />
                        ))
                    ) : (
                        <div className="col-span-full text-center py-12">
                            <p className="text-gray-500 text-lg">No rotations found</p>
                            <p className="text-gray-400 text-sm mt-1">Try adjusting your search or filter criteria</p>
                        </div>
                    )}
                </div>

                {/* Empty State */}
                {filteredRotations.length === 0 && (Array.isArray(rotations) ? rotations : rotations.data || []).length === 0 && (
                    <Card className="text-center py-12">
                        <CardContent>
                            <p className="text-gray-600 mb-4">No rotation patterns have been created yet</p>
                            <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Create First Rotation
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Create/Edit Modal */}
            <CreateEditRotationModal
                isOpen={isCreateModalOpen}
                onClose={handleCloseModal}
                rotation={editingRotation}
                departments={departments}
                pattern_templates={pattern_templates}
                onSave={handleSaveRotation}
            />

            {/* Assign Employees Modal */}
            <AssignEmployeesModal
                isOpen={isAssignModalOpen}
                onClose={() => {
                    setIsAssignModalOpen(false);
                    setSelectedRotationForAssignment(null);
                }}
                rotation={selectedRotationForAssignment}
            />

            {/* Rotation Details Modal */}
            <RotationDetailsModal
                isOpen={isDetailsModalOpen}
                onClose={handleCloseDetailsModal}
                rotation={selectedRotationForDetails}
                onEdit={handleEdit}
                onDelete={handleDelete}
                onDuplicate={handleDuplicate}
                onAssignEmployees={handleAssignEmployees}
            />
        </AppLayout>
    );
}

interface ProfileField {
    label: string;
    value: string | number;
}

interface ProfileSectionProps {
    fields: ProfileField[];
    columns?: 1 | 2 | 3 | 4;
}

export function ProfileSection({ fields, columns = 2 }: ProfileSectionProps) {
    const gridCols = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 md:grid-cols-2',
        3: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
    };

    return (
        <div className={`grid gap-4 ${gridCols[columns]}`}>
            {fields.map((field, index) => (
                <div key={index} className="space-y-1">
                    <p className="text-sm font-medium text-muted-foreground">
                        {field.label}
                    </p>
                    <p className="text-sm">
                        {field.value || 'N/A'}
                    </p>
                </div>
            ))}
        </div>
    );
}

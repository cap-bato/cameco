<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employees = \App\Models\Employee::with('profile')->orderBy('id')->get();

foreach ($employees as $e) {
    $p = $e->profile;
    echo sprintf(
        "ID: %d | %s | %s %s | gender: %s | photo: %s\n",
        $e->id,
        $e->employee_number,
        $p?->first_name ?? '',
        $p?->last_name ?? '',
        $p?->gender ?? 'NULL',
        $p?->profile_picture_path ?? 'NULL'
    );
}

echo "\nTotal: " . $employees->count() . " employees\n";

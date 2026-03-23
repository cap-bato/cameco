<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old check constraint
        DB::statement("ALTER TABLE employee_allowances DROP CONSTRAINT IF EXISTS employee_allowances_allowance_type_check;");
        // Add the new check constraint with 'clothing' included
        DB::statement(<<<SQL
            ALTER TABLE employee_allowances
            ADD CONSTRAINT employee_allowances_allowance_type_check
            CHECK ((allowance_type::text = ANY (
                ARRAY[
                    'rice'::character varying,
                    'cola'::character varying,
                    'transportation'::character varying,
                    'meal'::character varying,
                    'housing'::character varying,
                    'communication'::character varying,
                    'utilities'::character varying,
                    'laundry'::character varying,
                    'uniform'::character varying,
                    'medical'::character varying,
                    'educational'::character varying,
                    'special_project'::character varying,
                    'other'::character varying,
                    'clothing'::character varying
                ]::text[]
            )));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated check constraint
        DB::statement("ALTER TABLE employee_allowances DROP CONSTRAINT IF EXISTS employee_allowances_allowance_type_check;");
        // Restore the original check constraint (without 'clothing')
        DB::statement(<<<SQL
            ALTER TABLE employee_allowances
            ADD CONSTRAINT employee_allowances_allowance_type_check
            CHECK ((allowance_type::text = ANY (
                ARRAY[
                    'rice'::character varying,
                    'cola'::character varying,
                    'transportation'::character varying,
                    'meal'::character varying,
                    'housing'::character varying,
                    'communication'::character varying,
                    'utilities'::character varying,
                    'laundry'::character varying,
                    'uniform'::character varying,
                    'medical'::character varying,
                    'educational'::character varying,
                    'special_project'::character varying,
                    'other'::character varying
                ]::text[]
            )));
        SQL);
    }
};

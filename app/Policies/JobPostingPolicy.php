<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    /**
     * Determine if the user can view any job postings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.ats.job-postings.view') ||
               $user->can('hr.ats.job-postings.view') ||
               $user->can('recruitment.job_postings.view');
    }

    /**
     * Determine if the user can view a specific job posting.
     */
    public function view(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('admin.ats.job-postings.view') ||
               $user->can('hr.ats.job-postings.view') ||
               $user->can('recruitment.job_postings.view');
    }

    /**
     * Determine if the user can create job postings.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.ats.job-postings.create') ||
               $user->can('hr.ats.job-postings.create') ||
               $user->can('recruitment.job_postings.create');
    }

    /**
     * Determine if the user can update a job posting.
     */
    public function update(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('admin.ats.job-postings.edit') ||
               $user->can('hr.ats.job-postings.edit') ||
               $user->can('recruitment.job_postings.update');
    }

    /**
     * Determine if the user can delete a job posting.
     */
    public function delete(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('admin.ats.job-postings.delete') ||
               $user->can('hr.ats.job-postings.delete') ||
               $user->can('recruitment.job_postings.delete');
    }
}

import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Briefcase, MapPin, Calendar, Users, Search, Home } from 'lucide-react';

interface JobPosting {
  id: number;
  title: string;
  department_name: string;
  department_id: number;
  description: string;
  requirements: string;
  posted_at: string;
  applications_count: number;
}

interface Department {
  id: number;
  name: string;
}

interface PublicJobPostingsProps {
  jobPostings: JobPosting[];
  departments: Department[];
  filters: {
    department?: number;
    search?: string;
  };
}

export default function PublicJobPostingsIndex({
  jobPostings,
  departments,
  filters,
}: PublicJobPostingsProps) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedDepartment, setSelectedDepartment] = useState<string>(
    filters.department?.toString() || 'all'
  );

  const handleSearch = () => {
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (selectedDepartment !== 'all') params.append('department', selectedDepartment);
    
    window.location.href = `/job-postings?${params.toString()}`;
  };

  const truncateText = (text: string, maxLength: number) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  };

  return (
    <>
      <Head title="Job Openings - Cathay Metal Corporation" />
      
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
        {/* Header */}
        <header className="bg-white shadow-sm border-b">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-gray-900">
                  Cathay Metal Corporation
                </h1>
                <p className="text-gray-600 mt-1">Career Opportunities</p>
              </div>
              <Link href="/">
                <Button variant="outline" className="gap-2">
                  <Home className="h-4 w-4" />
                  Back to Home
                </Button>
              </Link>
            </div>
          </div>
        </header>

        {/* Hero Section */}
        <section className="bg-gradient-to-r from-blue-600 to-blue-800 text-white py-16">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 className="text-4xl font-bold mb-4">
              Join Our Team
            </h2>
            <p className="text-xl text-blue-100 max-w-3xl mx-auto">
              Discover exciting career opportunities at one of the leading steel manufacturing companies in the Philippines.
            </p>
          </div>
        </section>

        {/* Search and Filters */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 mb-12">
          <Card className="shadow-lg">
            <CardContent className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-2">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <Input
                      type="text"
                      placeholder="Search job titles..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                      className="pl-10"
                    />
                  </div>
                </div>
                <div className="flex gap-2">
                  <Select value={selectedDepartment} onValueChange={setSelectedDepartment}>
                    <SelectTrigger>
                      <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Departments</SelectItem>
                      {departments.map((dept) => (
                        <SelectItem key={dept.id} value={dept.id.toString()}>
                          {dept.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Button onClick={handleSearch} className="whitespace-nowrap">
                    Search
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Job Listings */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
          <div className="flex items-center justify-between mb-6">
            <h3 className="text-2xl font-semibold text-gray-900">
              Available Positions ({jobPostings.length})
            </h3>
          </div>

          {jobPostings.length === 0 ? (
            <Card className="text-center py-16">
              <CardContent>
                <Briefcase className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  No Open Positions
                </h3>
                <p className="text-gray-600">
                  There are currently no job openings matching your search criteria.
                </p>
                <p className="text-gray-600 mt-2">
                  Please check back later or adjust your filters.
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {jobPostings.map((job) => (
                <Card key={job.id} className="hover:shadow-lg transition-shadow">
                  <CardHeader>
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <CardTitle className="text-xl mb-2">
                          {job.title}
                        </CardTitle>
                        <div className="flex flex-wrap gap-3 text-sm text-gray-600">
                          <div className="flex items-center gap-1">
                            <MapPin className="h-4 w-4" />
                            {job.department_name}
                          </div>
                          <div className="flex items-center gap-1">
                            <Calendar className="h-4 w-4" />
                            Posted {job.posted_at}
                          </div>
                          <div className="flex items-center gap-1">
                            <Users className="h-4 w-4" />
                            {job.applications_count} applicants
                          </div>
                        </div>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <CardDescription className="mb-4">
                      {truncateText(job.description, 200)}
                    </CardDescription>
                    <Link href={`/job-postings/${job.id}`}>
                      <Button className="w-full">
                        View Details & Apply
                      </Button>
                    </Link>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <footer className="bg-gray-900 text-white py-8 mt-16">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p className="text-gray-400">
              © {new Date().getFullYear()} Cathay Metal Corporation. All rights reserved.
            </p>
          </div>
        </footer>
      </div>
    </>
  );
}

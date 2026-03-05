import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { ArrowRight, Users, Calendar, FileText, TrendingUp, Shield, Clock, UserCircle, Briefcase } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Cathay Metal Corporation - HRIS">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
                    rel="stylesheet"
                />
            </Head>
            
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800">
                {/* Header */}
                <header className="border-b border-slate-200 bg-white/95 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/95">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                        <div className="flex items-center gap-3">
                            <img src="/favicon.ico" alt="Cameco Logo" className="h-10 w-10" />
                            <div>
                                <h1 className="text-lg font-bold text-slate-900 dark:text-slate-100">
                                    Cathay Metal Corporation
                                </h1>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Human Resource Information System
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-center gap-4">
                            <AppearanceToggleDropdown />
                            <Button variant="outline" asChild className="gap-2">
                                <Link href="/job-postings">
                                    <Briefcase className="h-4 w-4" />
                                    Careers
                                </Link>
                            </Button>
                            {auth.user ? (
                                <Button asChild variant="default" className="bg-blue-600 hover:bg-blue-700">
                                    <Link href={dashboard()}>
                                        <span>Open Dashboard</span>
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <div className="flex items-center gap-3">
                                    <Button variant="ghost" asChild>
                                        <Link href={login()}>
                                            Sign In
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="mx-auto max-w-7xl px-6 py-12 lg:px-8 lg:py-16">
                    <div className="text-center">
                        <div className="mx-auto mb-6 flex h-30 w-30 items-center justify-center rounded-2xl shadow-lg">
                            <img src="/favicon.ico" alt="Cameco Logo" className="h-30 w-30" />
                        </div>
                        
                        <h1 className="mb-4 text-4xl font-bold text-slate-900 dark:text-slate-100 sm:text-5xl">
                            Welcome to <span className="text-blue-600">Cameco</span> HRIS
                        </h1>
                        
                        <p className="mx-auto mb-8 max-w-2xl text-lg text-slate-600 dark:text-slate-300">
                            Internal Human Resource Information System for Cathay Metal Corporation.
                            Streamline HR operations, manage employee data, and access your information securely.
                        </p>
                        
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {!auth.user ? (
                                <Button size="lg" asChild className="bg-blue-600 hover:bg-blue-700">
                                    <Link href={login()}>
                                        <UserCircle className="mr-2 h-5 w-5" />
                                        Sign In to Continue
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="lg" asChild className="bg-blue-600 hover:bg-blue-700">
                                    <Link href={dashboard()}>
                                        Go to Dashboard
                                        <ArrowRight className="ml-2 h-5 w-5" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* System Information Banner */}
                    <div className="mt-12 rounded-xl border border-blue-200 bg-blue-50/50 p-6 dark:border-blue-900 dark:bg-blue-950/30">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                                <Shield className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="mb-1 font-semibold text-slate-900 dark:text-slate-100">
                                    Internal System Notice
                                </h3>
                                <p className="text-sm text-slate-700 dark:text-slate-300">
                                    This is an internal, on-premise system for Cathay Metal Corporation employees and HR staff only. 
                                    All data is stored securely within our corporate network. Unauthorized access is prohibited and monitored.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    {/* Feature Grid - HR Modules */}
                    <div className="mt-16">
                        <div className="mb-8 text-center">
                            <h2 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                System Modules & Features
                            </h2>
                            <p className="mt-2 text-slate-600 dark:text-slate-400">
                                Comprehensive tools for HR management and employee self-service
                            </p>
                        </div>

                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {/* HR Manager Features */}
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <Users className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Employee Management
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Complete employee lifecycle management - from onboarding to offboarding. 
                                    Manage profiles, documents, and organizational structure.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <Clock className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Time & Attendance
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Track work hours, manage schedules, and monitor attendance. 
                                    Integrated timekeeping with payroll processing.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <FileText className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Leave Management
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Submit, approve, and track leave requests. Automated leave balance calculations 
                                    and holiday calendar management.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <TrendingUp className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Performance Reviews
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Conduct appraisals, set goals, and track employee performance. 
                                    360-degree feedback and competency assessments.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <Calendar className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Payroll Processing
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Automated payroll calculation with tax deductions, benefits, and allowances. 
                                    Generate payslips and statutory reports.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                    <Shield className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Security & Compliance
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Role-based access control, audit trails, and data protection. 
                                    Compliant with Philippine labor laws and data privacy regulations.
                                </p>
                            </div>
                            
                            <div className="group rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-blue-50/50 p-6 shadow-sm transition-all hover:shadow-md dark:border-blue-900 dark:from-blue-950/20 dark:to-blue-900/10 dark:bg-slate-900">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-600 text-white dark:bg-blue-700">
                                    <Briefcase className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    Career Opportunities
                                </h3>
                                <p className="mb-4 text-sm text-slate-600 dark:text-slate-400">
                                    Join Cathay Metal Corporation! Browse open positions, submit applications, 
                                    and explore exciting career opportunities with us.
                                </p>
                                <Button asChild variant="outline" size="sm" className="gap-2 border-blue-300 dark:border-blue-700">
                                    <Link href="/job-postings">
                                        View Open Positions
                                        <ArrowRight className="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                    
                
                    {/* Employee Portal Section  --to be implemented
                    <div className="mt-16 rounded-xl border border-slate-200 bg-gradient-to-br from-blue-50 to-slate-50 p-8 dark:border-slate-800 dark:from-blue-950/20 dark:to-slate-900/50">
                        <div className="text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-600">
                                <UserCircle className="h-8 w-8 text-white" />
                            </div>
                            <h2 className="mb-3 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                Employee Self-Service Portal
                            </h2>
                            <p className="mx-auto max-w-2xl text-slate-600 dark:text-slate-300">
                                Access your personal information, payslips, leave balances, and more. 
                                Submit requests, update details, and stay connected with company announcements.
                            </p>
                            <div className="mt-6 flex flex-wrap justify-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                <div className="flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-600"></div>
                                    <span>View Payslips</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-600"></div>
                                    <span>Request Leave</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-600"></div>
                                    <span>Update Profile</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-600"></div>
                                    <span>View Attendance</span>
                                </div>
                            </div>
                        </div>
                    </div>*/}
                </main>
                
                {/* Footer */}
                <footer className="border-t border-slate-200 bg-white/80 dark:border-slate-800 dark:bg-slate-900/80">
                    <div className="mx-auto max-w-7xl px-6 py-6 lg:px-8">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <p className="text-sm text-slate-600 dark:text-slate-400">
                                © 2025 Cathay Metal Corporation. All rights reserved.
                            </p>
                            <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <Shield className="h-4 w-4" />
                                <span>Internal System • On-Premise Deployment</span>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
} 


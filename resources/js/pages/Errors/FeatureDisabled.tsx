import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function FeatureDisabled({ module, message }: { module: string, message: string }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Feature Disabled', href: '#' }]}>
            <Head title="Feature Disabled" />
            <div className="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
                <div className="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 p-4 rounded-full mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">Module Disabled</h1>
                <p className="text-lg text-gray-600 dark:text-gray-400 max-w-md mb-8">
                    {message || `The ${module} module is currently disabled pending deployment.`}
                </p>
                <div className="flex space-x-4">
                    <Link 
                        href="/dashboard" 
                        className="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
                    >
                        Go to Main Dashboard
                    </Link>
                    <button 
                        onClick={() => window.history.back()}
                        className="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Go Back
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}

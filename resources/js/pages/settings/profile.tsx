// import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage, useForm } from '@inertiajs/react';

import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        name: auth.user.name || '',
        first_name: (auth.user?.profile as any)?.first_name ?? '',
        last_name: (auth.user?.profile as any)?.last_name ?? '',
        email: auth.user.email || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/profile/update', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h2 className="text-lg font-semibold">Profile information</h2>
                        <p className="text-sm text-muted-foreground">Update your name and email address</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                name="name"
                                required
                                autoComplete="name"
                                placeholder="Full name"
                                onChange={e => setData('name', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    className="mt-1 block w-full"
                                    value={data.first_name}
                                    name="first_name"
                                    placeholder="First name"
                                    onChange={e => setData('first_name', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.first_name} />
                            </div>
                            <div>
                                <Label htmlFor="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    className="mt-1 block w-full"
                                    value={data.last_name}
                                    name="last_name"
                                    placeholder="Last name"
                                    onChange={e => setData('last_name', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.last_name} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                name="email"
                                required
                                autoComplete="username"
                                placeholder="Email address"
                                onChange={e => setData('email', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div>
                                <p className="-mt-4 text-sm text-muted-foreground">
                                    Your email address is unverified.{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    >
                                        Click here to resend the verification email.
                                    </Link>
                                </p>
                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                Save
                            </Button>
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>

                    <DeleteUser />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

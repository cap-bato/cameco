// import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { useForm, Head } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/user-password';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/password/update', {
            preserveScroll: true,
            resetOnError: [
                'password',
                'password_confirmation',
                'current_password',
            ],
            resetOnSuccess: true,
            onError: (errors: any) => {
                if (errors.password) {
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h2 className="text-lg font-semibold">Update password</h2>
                        <p className="text-sm text-neutral-600">Ensure your account is using a long, random password to stay secure</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Current password
                            </Label>
                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                name="current_password"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="current-password"
                                placeholder="Current password"
                                value={data.current_password}
                                onChange={e => setData('current_password', e.target.value)}
                                disabled={processing}
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                New password
                            </Label>
                            <Input
                                id="password"
                                ref={passwordInput}
                                name="password"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="New password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                disabled={processing}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="Confirm password"
                                value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                disabled={processing}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing}
                                data-test="update-password-button"
                            >
                                Save password
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
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import { IconInput } from '@/components/icon-input';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface ForgotPasswordProps {
    status?: string;
    verifiedEmail?: string | null;
}

export default function ForgotPassword({ status, verifiedEmail }: ForgotPasswordProps) {
    const emailForm = useForm({ email: '' });
    const resetForm = useForm({
        email: verifiedEmail ?? '',
        password: '',
        password_confirmation: '',
    });

    const submitEmail: FormEventHandler = (e) => {
        e.preventDefault();
        emailForm.post(route('password.email'));
    };

    const submitReset: FormEventHandler = (e) => {
        e.preventDefault();
        resetForm.post(route('password.reset-direct'));
    };

    if (verifiedEmail) {
        return (
            <AuthLayout title="Set a new password" description={`Enter a new password for ${verifiedEmail}`}>
                <Head title="Reset password" />

                <div className="space-y-6">
                    <form onSubmit={submitReset}>
                        {/* include email as hidden input to ensure it's sent */}
                        <input type="hidden" name="email" value={resetForm.data.email} />

                        {/* show validation errors summary if present */}
                        {Object.keys(resetForm.errors).length > 0 && (
                            <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                <ul>
                                    {Object.entries(resetForm.errors).map(([k, v]) => (
                                        <li key={k}>{v as string}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <IconInput
                                    icon={Lock}
                                    id="password"
                                    type="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    value={resetForm.data.password}
                                    onChange={(e) => resetForm.setData('password', e.target.value)}
                                    placeholder="New password"
                                />
                                <InputError message={resetForm.errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm password</Label>
                                <IconInput
                                    icon={Lock}
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    autoComplete="new-password"
                                    value={resetForm.data.password_confirmation}
                                    onChange={(e) => resetForm.setData('password_confirmation', e.target.value)}
                                    placeholder="Confirm new password"
                                />
                                <InputError message={resetForm.errors.password_confirmation} />
                            </div>

                            <Button type="submit" className="w-full" disabled={resetForm.processing}>
                                {resetForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Reset password
                            </Button>
                        </div>
                    </form>

                    <div className="text-center text-sm text-muted-foreground">
                        <TextLink href={route('login')}>Back to log in</TextLink>
                    </div>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title="Forgot password" description="Enter your email to look up your account">
            <Head title="Forgot password" />

            {status && <div className="mb-4 text-center text-sm font-medium text-gold">{status}</div>}

            <div className="space-y-6">
                <form onSubmit={submitEmail}>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <IconInput
                            icon={Mail}
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            value={emailForm.data.email}
                            autoFocus
                            onChange={(e) => emailForm.setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />

                        <InputError message={emailForm.errors.email} />
                    </div>

                    <div className="my-6 flex items-center justify-start">
                        <Button className="w-full" disabled={emailForm.processing}>
                            {emailForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Continue
                        </Button>
                    </div>
                </form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href={route('login')}>log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}

import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import PartyForm from '@/components/party-form';
import '../../../css/parties.css';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Parties', href: '/parties' },
    { title: 'Add Party', href: '/parties/create' },
];

export default function PartyCreate({ partyTypes = [] }: { partyTypes: any[] }) {
    const { data, setData, post, processing, errors } = useForm({
        party_type_id: '',
        name: '',
        phone: '',
        address: '',
        notes: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/parties');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Party" />

            <div className="pty-form-page">
                <div className="pty-form-head">
                    <h1 className="pty-form-title">Add Party</h1>
                    <p className="pty-form-desc">Create a customer, karigar, wholesaler, other shop or company record.</p>
                </div>

                <PartyForm
                    data={data}
                    setData={setData as any}
                    errors={errors as any}
                    processing={processing}
                    partyTypes={partyTypes}
                    onSubmit={submit}
                    submitLabel="Create Party"
                />
            </div>
        </AppLayout>
    );
}

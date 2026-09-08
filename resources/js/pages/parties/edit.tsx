import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import PartyForm from '@/components/party-form';
import '../../../css/parties.css';

export default function PartyEdit({ party, partyTypes = [] }: { party: any; partyTypes: any[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Parties', href: '/parties' },
        { title: party.name, href: `/parties/${party.id}` },
        { title: 'Edit', href: `/parties/${party.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        party_type_id: party.party_type_id ?? '',
        name: party.name ?? '',
        phone: party.phone ?? '',
        address: party.address ?? '',
        notes: party.notes ?? '',
        is_active: Boolean(party.is_active),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/parties/${party.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${party.name}`} />

            <div className="pty-form-page">
                <div className="pty-form-head">
                    <h1 className="pty-form-title">Edit Party</h1>
                    <p className="pty-form-desc">Update details for {party.name}.</p>
                </div>

                <PartyForm
                    data={data}
                    setData={setData as any}
                    errors={errors as any}
                    processing={processing}
                    partyTypes={partyTypes}
                    onSubmit={submit}
                    submitLabel="Save Changes"
                    showActiveToggle
                />
            </div>
        </AppLayout>
    );
}

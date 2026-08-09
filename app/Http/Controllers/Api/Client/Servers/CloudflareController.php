import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import http from '@/api/http';
import Spinner from '@/components/elements/Spinner';
import Switch from '@/components/elements/Switch';

interface DNSRecord {
    id: string;
    name: string;
    content: string;
    proxied: boolean;
}

export default () => {
    const uuid = ServerContext.useStoreState(state => state.server.data!.uuid);
    const [loading, setLoading] = useState(true);
    const [record, setRecord] = useState<DNSRecord | null>(null);

    useEffect(() => {
        http.get(`/api/client/servers/${uuid}/cloudflare`)
            .then(({ data }) => setRecord(data.record))
            .finally(() => setLoading(false));
    }, [uuid]);

    const toggleProxy = (proxied: boolean) => {
        setLoading(true);
        http.post(`/api/client/servers/${uuid}/cloudflare/proxy`, { proxied })
            .then(({ data }) => setRecord(data.record))
            .finally(() => setLoading(false));
    };

    if (loading) return <Spinner size={'large'} centered />;

    return (
        <div className={'bg-neutral-800 p-6 rounded-lg shadow-md'}>
            <h2 className={'text-xl font-bold text-neutral-100 mb-4'}>Cloudflare DNS & Proxy Kezelő</h2>
            {record ? (
                <div className={'flex items-center justify-between'}>
                    <div>
                        <p className={'text-neutral-300'}>DNS Rekord: <code className={'text-yellow-400'}>{record.name}</code></p>
                        <p className={'text-neutral-300'}>Mögöttes IP: <code className={'text-green-400'}>{record.content}</code></p>
                    </div>
                    <div className={'flex items-center space-x-3'}>
                        <span className={'text-sm text-neutral-400'}>Cloudflare Proxy (Orange Cloud)</span>
                        <Switch
                            name={'proxied'}
                            checked={record.proxied}
                            onChange={e => toggleProxy(e.target.checked)}
                        />
                    </div>
                </div>
            ) : (
                <p className={'text-neutral-400'}>Nincs aktív Cloudflare DNS rekord ehhez a szerverhez.</p>
            )}
        </div>
    );
};

import React, { useState, useEffect } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import axios from 'axios';

export default () => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const { addFlash, clearFlashes } = useFlash();
    
    const [motd, setMotd] = useState('');
    const [serverIcon, setServerIcon] = useState('default');
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (!serverId) return;
        
        axios.get(`/auth/design_backend.php?server_id=${serverId}`)
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    setMotd(response.data.motd || '');
                    setServerIcon(response.data.icon || 'default');
                } else {
                    addFlash({ key: 'server-design', type: 'error', message: response.data.message || 'Nem sikerült betölteni a dizájn beállításokat.' });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'server-design', type: 'error', message: 'Hiba történt a dizájn adatok lekérése közben.' });
            })
            .finally(() => {
                setIsLoading(false);
            });
    }, [serverId]);

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        clearFlashes('server-design');

        axios.post('/auth/design_backend.php', {
            server_id: serverId,
            motd: motd,
            icon: serverIcon
        })
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    addFlash({ key: 'server-design', type: 'success', message: 'A szerver megjelenése sikeresen frissítve lett!' });
                } else {
                    addFlash({ key: 'server-design', type: 'error', message: response.data.message || 'Hiba történt a mentés során.' });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'server-design', type: 'error', message: 'Nem sikerült elküldeni a módosításokat a háttérmotornak.' });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={'Server Design'}>
            <FlashMessageRender byKey={'server-design'} css={tw`mb-4`} />

            {isLoading ? (
                <div css={tw`text-center text-neutral-400 py-8`}>Megjelenítési beállítások betöltése...</div>
            ) : (
                <div css={tw`bg-neutral-900 border border-neutral-800 rounded-xl p-6 max-w-xl mx-auto shadow-xl`}>
                    <p css={tw`text-sm text-neutral-400 mb-6 leading-relaxed`}>
                        Szabd testre a szervered külső megjelenését a szerverlistában. Itt kényelmesen beállíthatod a több sorközt támogató leírást (MOTD).
                    </p>

                    <form onSubmit={handleFormSubmit} css={tw`space-y-4`}>
                        <div css={tw`flex flex-col`}>
                            <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Szerver Leírása (MOTD)</label>
                            <input 
                                type="text"
                                value={motd}
                                onChange={(e) => setMotd(e.target.value)}
                                placeholder="A DavidGames Minecraft Szervere"
                                css={tw`w-full bg-neutral-950 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                            />
                        </div>

                        <div css={tw`flex flex-col`}>
                            <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Szerver Ikon Típusa</label>
                            <select
                                value={serverIcon}
                                onChange={(e) => setServerIcon(e.target.value)}
                                css={tw`w-full bg-neutral-950 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                            >
                                <option value="default">Default (Gyári DavidGames logó)</option>
                                <option value="custom">Egyedi (A szervermappába feltöltött server-icon.png)</option>
                            </select>
                        </div>

                        <div css={tw`pt-4`}>
                            <button 
                                type="submit" 
                                disabled={isSubmitting}
                                style={{ backgroundColor: '#10b981', color: '#064e3b', fontWeight: 'bold' }}
                                css={tw`w-full py-3 rounded-md border-none cursor-pointer text-sm transition-colors text-center disabled:opacity-50`}
                            >
                                {isSubmitting ? 'Mentés folyamatban...' : 'Megjelenés Élesítése'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </ServerContentBlock>
    );
};

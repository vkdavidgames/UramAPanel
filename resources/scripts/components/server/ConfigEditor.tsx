import React, { useState, useEffect } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import axios from 'axios';

interface ConfigProps {
    [key: string]: string;
}

export default () => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const { addFlash, clearFlashes } = useFlash();
    
    const [config, setConfig] = useState<ConfigProps>({});
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (!serverId) return;
        
        axios.get(`/auth/config_backend.php?server_id=${serverId}`)
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    setConfig(response.data.config);
                } else {
                    addFlash({ key: 'config-editor', type: 'error', message: response.data.message || 'Nem sikerült beolvasni a konfigurációt.' });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'config-editor', type: 'error', message: 'Hiba történt a konfigurációs fájl beolvasása közben.' });
            })
            .finally(() => {
                setIsLoading(false);
            });
    }, [serverId]);

    const handleInputChange = (key: string, value: string) => {
        setConfig((prev) => ({ ...prev, [key]: value }));
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        clearFlashes('config-editor');

        axios.post('/auth/config_backend.php', {
            server_id: serverId,
            config: config
        })
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    addFlash({ key: 'config-editor', type: 'success', message: 'A beállítások sikeresen mentve lettek és érvényesülnek a következő indításkor!' });
                } else {
                    addFlash({ key: 'config-editor', type: 'error', message: response.data.message || 'Ismeretlen hiba történt a mentés során.' });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'config-editor', type: 'error', message: 'Nem sikerült menteni a beállításokat a háttérben.' });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={'Config Editor'}>
            <FlashMessageRender byKey={'config-editor'} css={tw`mb-4`} />
            
            {isLoading ? (
                <div css={tw`text-center text-neutral-400 py-8`}>Konfigurációs adatok betöltése és elemzése...</div>
            ) : (
                <div css={tw`bg-neutral-900 border border-neutral-800 rounded-xl p-6 max-w-xl mx-auto shadow-xl`}>
                    <p css={tw`text-sm text-neutral-400 mb-6 leading-relaxed`}>
                        Módosítsd a szervered alapvető tulajdonságait anélkül, hogy a fájlkezelőben kellene manuálisan szerkesztened a beállításokat.
                    </p>

                    <form onSubmit={handleFormSubmit} css={tw`space-y-4`}>
                        <div css={tw`grid grid-cols-1 gap-4`}>
                            <div css={tw`flex flex-col`}>
                                <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Maximális Játékosszám (max-players)</label>
                                <input 
                                    type="text" 
                                    value={config['max-players'] || '20'}
                                    onChange={(e) => handleInputChange('max-players', e.target.value)}
                                    css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                                />
                            </div>

                            <div css={tw`flex flex-col`}>
                                <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Játék Nehézsége (difficulty)</label>
                                <select 
                                    value={config['difficulty'] || 'easy'} 
                                    onChange={(e) => handleInputChange('difficulty', e.target.value)}
                                    css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                                >
                                    <option value="peaceful">Peaceful (Békés)</option>
                                    <option value="easy">Easy (Könnyű)</option>
                                    <option value="normal">Normal (Normál)</option>
                                    <option value="hard">Hard (Nehéz)</option>
                                </select>
                            </div>

                            <div css={tw`flex flex-col`}>
                                <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Játékosok közötti harc (pvp)</label>
                                <select 
                                    value={config['pvp'] || 'true'} 
                                    onChange={(e) => handleInputChange('pvp', e.target.value)}
                                    css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                                >
                                    <option value="true">Bekapcsolva (Engedélyezett)</option>
                                    <option value="false">Kikapcsolva (Tiltott)</option>
                                </select>
                            </div>

                            <div css={tw`flex flex-col`}>
                                <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Online Mód / Prémium ellenőrzés (online-mode)</label>
                                <select 
                                    value={config['online-mode'] || 'true'} 
                                    onChange={(e) => handleInputChange('online-mode', e.target.value)}
                                    css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                                >
                                    <option value="true">Csak Prémium (Eredeti Minecraft)</option>
                                    <option value="false">Tört Mód (Minden kliens beléphet)</option>
                                </select>
                            </div>
                        </div>

                        <div css={tw`pt-4`}>
                            <button 
                                type="submit" 
                                disabled={isSubmitting}
                                style={{ backgroundColor: '#10b981', color: '#064e3b', fontWeight: 'bold' }}
                                css={tw`w-full py-3 rounded-md border-none cursor-pointer text-sm transition-colors text-center disabled:opacity-50`}
                            >
                                {isSubmitting ? 'Mentés folyamatban...' : 'Módosítások Mentése'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </ServerContentBlock>
    );
};

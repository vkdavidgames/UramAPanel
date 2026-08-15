import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import axios from 'axios';

export default () => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const { addFlash, clearFlashes } = useFlash();
    
    const [playerName, setPlayerName] = useState('');
    const [actionType, setActionType] = useState('whitelist-add');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!playerName.trim()) {
            addFlash({ key: 'player-manager', type: 'error', message: 'A játékos nevének kitöltése kötelező!' });
            return;
        }

        setIsSubmitting(true);
        clearFlashes('player-manager');

        const formData = new FormData();
        formData.append('server_id', String(serverId));
        formData.append('player_name', playerName.trim());
        formData.append('action_type', actionType);

        axios.post('/auth/player_backend.php', formData)
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    addFlash({ key: 'player-manager', type: 'success', message: response.data.message });
                    setPlayerName('');
                } else {
                    addFlash({ key: 'player-manager', type: 'error', message: response.data.message || 'Hiba történt a parancs végrehajtása során.' });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'player-manager', type: 'error', message: 'Nem sikerült kapcsolódni a játékoskezelő háttérmotorhoz.' });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={'Player Manager'}>
            <FlashMessageRender byKey={'player-manager'} css={tw`mb-4`} />
            <div css={tw`bg-neutral-900 border border-neutral-800 rounded-xl p-6 max-w-xl mx-auto shadow-xl`}>
                <p css={tw`text-sm text-neutral-400 mb-6 leading-relaxed`}>
                    Kezeld a szervereden lévő játékosokat közvetlenül a felületről. A kiválasztott művelet automatikusan végrehajtódik parancsként az éles konzolban.
                </p>

                <form onSubmit={handleFormSubmit} css={tw`space-y-4`}>
                    <div css={tw`flex flex-col`}>
                        <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Művelet Kiválasztása</label>
                        <select 
                            value={actionType} 
                            onChange={(e) => setActionType(e.target.value)}
                            css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                        >
                            <option value="whitelist-add">Hozzáadás a Fehérlistához (whitelist add)</option>
                            <option value="whitelist-remove">Eltávolítás a Fehérlistáról (whitelist remove)</option>
                            <option value="op">Operátori jog megadása (op)</option>
                            <option value="deop">Operátori jog elvétele (deop)</option>
                            <option value="kick">Játékos kirúgása (kick)</option>
                            <option value="ban">Játékos kitiltása (ban)</option>
                        </select>
                    </div>

                    <div css={tw`flex flex-col`}>
                        <label css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Játékos Neve (Ingame név)</label>
                        <input 
                            type="text" 
                            value={playerName}
                            onChange={(e) => setPlayerName(e.target.value)}
                            placeholder="Pl. DavidGames vagy Uram"
                            css={tw`w-full bg-neutral-900 border border-neutral-800 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-green-500`}
                        />
                    </div>

                    <div css={tw`pt-4`}>
                        <button 
                            type="submit" 
                            disabled={isSubmitting}
                            style={{ backgroundColor: '#10b981', color: '#064e3b', fontWeight: 'bold' }}
                            css={tw`w-full py-3 rounded-md border-none cursor-pointer text-sm transition-colors text-center disabled:opacity-50`}
                        >
                            {isSubmitting ? 'Parancs küldése...' : 'Művelet Végrehajtása'}
                        </button>
                    </div>
                </form>
            </div>
        </ServerContentBlock>
    );
};

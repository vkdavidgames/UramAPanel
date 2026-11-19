import { useTranslation } from 'react-i18next';
import React, { useState } from 'react';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import Input from '@/components/elements/Input';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import axios from 'axios';

export default () => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const { i18n } = useTranslation();
    const storeLang = useStoreState((state) => state.user.data?.language || '');
    const userLang = localStorage.getItem('selectedLanguage') || i18n.language || storeLang;
    const isHu = (userLang || (typeof navigator !== 'undefined' ? navigator.language : 'en'))
        .toLowerCase()
        .startsWith('hu');

    const t = {
        pageTitle: isHu ? 'Játékos Kezelő' : 'Player Manager',
        boxTitle: isHu ? 'Játékos Műveletek (Élő Konzol)' : 'Player Actions (Live Console)',
        description: isHu
            ? 'Kezeld a szervereden lévő játékosokat közvetlenül a felületről. A kiválasztott művelet automatikusan végrehajtódik parancsként az éles konzolban.'
            : 'Manage players on your server directly from the panel. The selected action runs automatically as a command in the live console.',
        nameRequired: isHu ? 'A játékos nevének kitöltése kötelező!' : 'Player name is required!',
        execErrFallback: isHu
            ? 'Hiba történt a parancs végrehajtása során.'
            : 'An error occurred while executing the command.',
        connErr: isHu
            ? 'Nem sikerült kapcsolódni a játékoskezelő háttérmotorhoz.'
            : 'Failed to connect to the player manager backend.',
        selectAction: isHu ? 'Művelet Kiválasztása' : 'Select Action',
        whitelistAdd: isHu ? 'Hozzáadás a Fehérlistához (whitelist add)' : 'Add to Whitelist (whitelist add)',
        whitelistRemove: isHu ? 'Eltávolítás a Fehérlistáról (whitelist remove)' : 'Remove from Whitelist (whitelist remove)',
        op: isHu ? 'Operátori jog megadása (op)' : 'Grant Operator (op)',
        deop: isHu ? 'Operátori jog elvétele (deop)' : 'Revoke Operator (deop)',
        kick: isHu ? 'Játékos kirúgása (kick)' : 'Kick Player (kick)',
        ban: isHu ? 'Játékos kitiltása (ban)' : 'Ban Player (ban)',
        playerNameLabel: isHu ? 'Játékos Neve (Ingame név)' : 'Player Name (In-game username)',
        playerPlaceholder: isHu ? 'Pl. DavidGames vagy Uram' : 'e.g. DavidGames or Uram',
        submitBtn: isHu ? 'Művelet Végrehajtása' : 'Execute Action',
    };

    const { addFlash, clearFlashes } = useFlash();

    const [playerName, setPlayerName] = useState('');
    const [actionType, setActionType] = useState('whitelist-add');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!playerName.trim()) {
            addFlash({ key: 'player-manager', type: 'error', message: t.nameRequired });
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
                    addFlash({
                        key: 'player-manager',
                        type: 'error',
                        message: response.data.message || t.execErrFallback,
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({ key: 'player-manager', type: 'error', message: t.connErr });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={t.pageTitle}>
            <FlashMessageRender byKey={'player-manager'} css={tw`mb-4`} />

            <div css={tw`max-w-xl mx-auto`}>
                <form onSubmit={handleFormSubmit}>
                    <TitledGreyBox title={t.boxTitle}>
                        <p css={tw`text-sm text-neutral-300 mb-6 leading-relaxed`}>
                            {t.description}
                        </p>

                        <div css={tw`space-y-4`}>
                            <div>
                                <Label>{t.selectAction}</Label>
                                <Select
                                    value={actionType}
                                    onChange={(e) => setActionType(e.target.value)}
                                >
                                    <option value={'whitelist-add'}>{t.whitelistAdd}</option>
                                    <option value={'whitelist-remove'}>{t.whitelistRemove}</option>
                                    <option value={'op'}>{t.op}</option>
                                    <option value={'deop'}>{t.deop}</option>
                                    <option value={'kick'}>{t.kick}</option>
                                    <option value={'ban'}>{t.ban}</option>
                                </Select>
                            </div>

                            <div>
                                <Label>{t.playerNameLabel}</Label>
                                <Input
                                    type={'text'}
                                    value={playerName}
                                    onChange={(e) => setPlayerName(e.target.value)}
                                    placeholder={t.playerPlaceholder}
                                />
                            </div>
                        </div>

                        <div css={tw`flex justify-end mt-6`}>
                            <Button type={'submit'} size={'large'} isLoading={isSubmitting} disabled={isSubmitting}>
                                {t.submitBtn}
                            </Button>
                        </div>
                    </TitledGreyBox>
                </form>
            </div>
        </ServerContentBlock>
    );
};

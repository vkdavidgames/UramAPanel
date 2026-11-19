import { useTranslation } from 'react-i18next';
import React, { useState, useEffect } from 'react';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import Button from '@/components/elements/Button';
import Spinner from '@/components/elements/Spinner';
import tw from 'twin.macro';
import axios from 'axios';

interface ConfigProps {
    [key: string]: string;
}

export default () => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const { i18n } = useTranslation();
    const storeLang = useStoreState((state) => state.user.data?.language || '');
    const userLang = localStorage.getItem('selectedLanguage') || i18n.language || storeLang;
    const isHu = (userLang || (typeof navigator !== 'undefined' ? navigator.language : 'en'))
        .toLowerCase()
        .startsWith('hu');

    const t = {
        pageTitle: isHu ? 'Konfig Szerkesztő' : 'Config Editor',
        boxTitle: isHu ? 'Szerver Beállítások (server.properties)' : 'Server Settings (server.properties)',
        description: isHu
            ? 'Módosítsd a szervered alapvető tulajdonságait anélkül, hogy a fájlkezelőben kellene manuálisan szerkesztened a beállításokat.'
            : 'Modify your server’s core properties without having to manually edit files in the file manager.',
        loading: isHu ? 'Konfigurációs adatok betöltése és elemzése...' : 'Loading and parsing configuration...',
        loadErrFallback: isHu ? 'Nem sikerült beolvasni a konfigurációt.' : 'Failed to read configuration.',
        loadErrCatch: isHu
            ? 'Hiba történt a konfigurációs fájl beolvasása közben.'
            : 'An error occurred while reading the configuration file.',
        saveSuccess: isHu
            ? 'A beállítások sikeresen mentve lettek és érvényesülnek a következő indításkor!'
            : 'Settings saved successfully and will take effect on the next server start!',
        saveErrFallback: isHu ? 'Ismeretlen hiba történt a mentés során.' : 'An unknown error occurred while saving.',
        saveErrCatch: isHu
            ? 'Nem sikerült menteni a beállításokat a háttérben.'
            : 'Failed to save settings in the background.',
        maxPlayers: isHu ? 'Maximális Játékosszám (max-players)' : 'Max Players (max-players)',
        difficulty: isHu ? 'Játék Nehézsége (difficulty)' : 'Game Difficulty (difficulty)',
        diffPeaceful: isHu ? 'Peaceful (Békés)' : 'Peaceful',
        diffEasy: isHu ? 'Easy (Könnyű)' : 'Easy',
        diffNormal: isHu ? 'Normal (Normál)' : 'Normal',
        diffHard: isHu ? 'Hard (Nehéz)' : 'Hard',
        pvp: isHu ? 'Játékosok közötti harc (pvp)' : 'Player vs Player (pvp)',
        pvpOn: isHu ? 'Bekapcsolva (Engedélyezett)' : 'Enabled (Allowed)',
        pvpOff: isHu ? 'Kikapcsolva (Tiltott)' : 'Disabled (Not allowed)',
        onlineMode: isHu ? 'Online Mód / Prémium ellenőrzés (online-mode)' : 'Online Mode / Premium Check (online-mode)',
        onlineTrue: isHu ? 'Csak Prémium (Eredeti Minecraft)' : 'Premium Only (Official Minecraft)',
        onlineFalse: isHu ? 'Tört Mód (Minden kliens beléphet)' : 'Cracked Mode (All clients allowed)',
        saveButton: isHu ? 'Módosítások Mentése' : 'Save Changes',
    };

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
                    addFlash({
                        key: 'config-editor',
                        type: 'error',
                        message: response.data.message || t.loadErrFallback,
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'config-editor',
                    type: 'error',
                    message: t.loadErrCatch,
                });
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
            config: config,
        })
            .then((response) => {
                if (response.data && response.data.status === 'success') {
                    addFlash({
                        key: 'config-editor',
                        type: 'success',
                        message: t.saveSuccess,
                    });
                } else {
                    addFlash({
                        key: 'config-editor',
                        type: 'error',
                        message: response.data.message || t.saveErrFallback,
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'config-editor',
                    type: 'error',
                    message: t.saveErrCatch,
                });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={t.pageTitle}>
            <FlashMessageRender byKey={'config-editor'} css={tw`mb-4`} />

            {isLoading ? (
                <Spinner size={'large'} centered />
            ) : (
                <div css={tw`max-w-xl mx-auto`}>
                    <form onSubmit={handleFormSubmit}>
                        <TitledGreyBox title={t.boxTitle}>
                            <p css={tw`text-sm text-neutral-300 mb-6 leading-relaxed`}>
                                {t.description}
                            </p>

                            <div css={tw`space-y-4`}>
                                <div>
                                    <Label>{t.maxPlayers}</Label>
                                    <Input
                                        type={'number'}
                                        value={config['max-players'] || '20'}
                                        onChange={(e) => handleInputChange('max-players', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label>{t.difficulty}</Label>
                                    <Select
                                        value={config['difficulty'] || 'easy'}
                                        onChange={(e) => handleInputChange('difficulty', e.target.value)}
                                    >
                                        <option value={'peaceful'}>{t.diffPeaceful}</option>
                                        <option value={'easy'}>{t.diffEasy}</option>
                                        <option value={'normal'}>{t.diffNormal}</option>
                                        <option value={'hard'}>{t.diffHard}</option>
                                    </Select>
                                </div>

                                <div>
                                    <Label>{t.pvp}</Label>
                                    <Select
                                        value={config['pvp'] || 'true'}
                                        onChange={(e) => handleInputChange('pvp', e.target.value)}
                                    >
                                        <option value={'true'}>{t.pvpOn}</option>
                                        <option value={'false'}>{t.pvpOff}</option>
                                    </Select>
                                </div>

                                <div>
                                    <Label>{t.onlineMode}</Label>
                                    <Select
                                        value={config['online-mode'] || 'true'}
                                        onChange={(e) => handleInputChange('online-mode', e.target.value)}
                                    >
                                        <option value={'true'}>{t.onlineTrue}</option>
                                        <option value={'false'}>{t.onlineFalse}</option>
                                    </Select>
                                </div>
                            </div>

                            <div css={tw`flex justify-end mt-6`}>
                                <Button type={'submit'} size={'large'} isLoading={isSubmitting} disabled={isSubmitting}>
                                    {t.saveButton}
                                </Button>
                            </div>
                        </TitledGreyBox>
                    </form>
                </div>
            )}
        </ServerContentBlock>
    );
};

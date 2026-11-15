import React, { useState, useEffect } from 'react';
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
                        message: response.data.message || 'Nem sikerült beolvasni a konfigurációt.',
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'config-editor',
                    type: 'error',
                    message: 'Hiba történt a konfigurációs fájl beolvasása közben.',
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
                        message: 'A beállítások sikeresen mentve lettek és érvényesülnek a következő indításkor!',
                    });
                } else {
                    addFlash({
                        key: 'config-editor',
                        type: 'error',
                        message: response.data.message || 'Ismeretlen hiba történt a mentés során.',
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'config-editor',
                    type: 'error',
                    message: 'Nem sikerült menteni a beállításokat a háttérben.',
                });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    return (
        <ServerContentBlock title={'Config Editor'}>
            <FlashMessageRender byKey={'config-editor'} css={tw`mb-4`} />

            {isLoading ? (
                <Spinner size={'large'} centered />
            ) : (
                <div css={tw`max-w-xl mx-auto`}>
                    <form onSubmit={handleFormSubmit}>
                        <TitledGreyBox title={'Szerver Beállítások (server.properties)'}>
                            <p css={tw`text-sm text-neutral-300 mb-6 leading-relaxed`}>
                                Módosítsd a szervered alapvető tulajdonságait anélkül, hogy a fájlkezelőben kellene manuálisan szerkesztened a beállításokat.
                            </p>

                            <div css={tw`space-y-4`}>
                                <div>
                                    <Label>Maximális Játékosszám (max-players)</Label>
                                    <Input
                                        type={'number'}
                                        value={config['max-players'] || '20'}
                                        onChange={(e) => handleInputChange('max-players', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label>Játék Nehézsége (difficulty)</Label>
                                    <Select
                                        value={config['difficulty'] || 'easy'}
                                        onChange={(e) => handleInputChange('difficulty', e.target.value)}
                                    >
                                        <option value={'peaceful'}>Peaceful (Békés)</option>
                                        <option value={'easy'}>Easy (Könnyű)</option>
                                        <option value={'normal'}>Normal (Normál)</option>
                                        <option value={'hard'}>Hard (Nehéz)</option>
                                    </Select>
                                </div>

                                <div>
                                    <Label>Játékosok közötti harc (pvp)</Label>
                                    <Select
                                        value={config['pvp'] || 'true'}
                                        onChange={(e) => handleInputChange('pvp', e.target.value)}
                                    >
                                        <option value={'true'}>Bekapcsolva (Engedélyezett)</option>
                                        <option value={'false'}>Kikapcsolva (Tiltott)</option>
                                    </Select>
                                </div>

                                <div>
                                    <Label>Online Mód / Prémium ellenőrzés (online-mode)</Label>
                                    <Select
                                        value={config['online-mode'] || 'true'}
                                        onChange={(e) => handleInputChange('online-mode', e.target.value)}
                                    >
                                        <option value={'true'}>Csak Prémium (Eredeti Minecraft)</option>
                                        <option value={'false'}>Tört Mód (Minden kliens beléphet)</option>
                                    </Select>
                                </div>
                            </div>

                            <div css={tw`flex justify-end mt-6`}>
                                <Button type={'submit'} size={'large'} isLoading={isSubmitting} disabled={isSubmitting}>
                                    Módosítások Mentése
                                </Button>
                            </div>
                        </TitledGreyBox>
                    </form>
                </div>
            )}
        </ServerContentBlock>
    );
};

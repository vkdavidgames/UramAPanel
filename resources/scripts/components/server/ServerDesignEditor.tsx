import { useTranslation } from 'react-i18next';
import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import http from '@/api/http';

export default () => {
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name) || 'Minecraft Server';
    const serverUuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const { i18n } = useTranslation();
    const storeLang = useStoreState((state) => state.user.data?.language || '');
    const userLang = localStorage.getItem('selectedLanguage') || i18n.language || storeLang;
    const isHu = (userLang || (typeof navigator !== 'undefined' ? navigator.language : 'en'))
        .toLowerCase()
        .startsWith('hu');

    const t = {
        pageTitle: isHu ? 'Szerver Kinézet' : 'Server Design',
        boxTitle: isHu ? 'Szerver Megjelenés és MOTD Szerkesztő' : 'Server Appearance & MOTD Editor',
        previewTitle: isHu ? 'Élő Előnézet (Minecraft Szerverlista)' : 'Live Preview (Minecraft Server List)',
        colorsLabel: isHu ? 'Vizuális Minecraft Színválasztó Gombok' : 'Visual Minecraft Color Buttons',
        motdLabel: isHu ? 'Szerver Leírása (MOTD szöveg)' : 'Server Description (MOTD text)',
        motdPlaceholder: isHu ? '&aDavidGames &f| &bSzerverünk &lONLINE' : '&aDavidGames &f| &bOur Server is &lONLINE',
        iconLabel: isHu ? 'Szerver Ikon Feltöltés (server-icon.png)' : 'Server Icon Upload (server-icon.png)',
        iconHint: isHu ? '64×64 px PNG ajánlott (automatikusan server-icon.png néven mentve).' : '64×64 px PNG recommended (automatically saved as server-icon.png).',
        uploading: isHu ? 'Feltöltés folyamatban...' : 'Uploading icon...',
        saveButton: isHu ? 'MOTD Mentése' : 'Save MOTD',
        defaultMotd: isHu ? 'A DavidGames Minecraft Szervere' : 'A DavidGames Minecraft Server',
        readErr: isHu
            ? 'Nem sikerült beolvasni a server.properties fájlt. Győződj meg róla, hogy a fájl létezik!'
            : 'Failed to read server.properties. Make sure the file exists!',
        uploadSuccess: isHu
            ? 'Az egyedi server-icon.png sikeresen feltöltve a gyökérkönyvtárba!'
            : 'Custom server-icon.png successfully uploaded to the root directory!',
        uploadErr: isHu ? 'Hiba történt az ikon feltöltése során: ' : 'Error uploading icon: ',
        saveSuccess: isHu
            ? 'A MOTD sikeresen elmentve a server.properties fájlba!'
            : 'MOTD successfully saved to server.properties!',
        saveErr: isHu ? 'Hiba a mentés során: ' : 'Error while saving: ',
    };

    const { addFlash, clearFlashes } = useFlash();
    const inputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [motd, setMotd] = useState('');
    const [previewIconUrl, setPreviewIconUrl] = useState('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isUploading, setIsUploading] = useState(false);

    const mcColors = [
        { code: '&0', name: isHu ? 'Fekete' : 'Black', hex: '#000000' },
        { code: '&1', name: isHu ? 'Sötétkék' : 'Dark Blue', hex: '#0000aa' },
        { code: '&2', name: isHu ? 'Sötétzöld' : 'Dark Green', hex: '#00aa00' },
        { code: '&3', name: isHu ? 'Sötétcián' : 'Dark Aqua', hex: '#00aaaa' },
        { code: '&4', name: isHu ? 'Sötétvörös' : 'Dark Red', hex: '#aa0000' },
        { code: '&5', name: isHu ? 'Lila' : 'Dark Purple', hex: '#aa00aa' },
        { code: '&6', name: isHu ? 'Arany' : 'Gold', hex: '#ffaa00' },
        { code: '&7', name: isHu ? 'Szürke' : 'Gray', hex: '#aaaaaa' },
        { code: '&8', name: isHu ? 'Sötétszürke' : 'Dark Gray', hex: '#555555' },
        { code: '&9', name: isHu ? 'Kék' : 'Blue', hex: '#5555ff' },
        { code: '&a', name: isHu ? 'Zöld' : 'Green', hex: '#55ff55' },
        { code: '&b', name: 'Aqua', hex: '#55ffff' },
        { code: '&c', name: isHu ? 'Vörös' : 'Red', hex: '#ff5555' },
        { code: '&d', name: isHu ? 'Rózsaszín' : 'Light Purple', hex: '#ff55ff' },
        { code: '&e', name: isHu ? 'Sárga' : 'Yellow', hex: '#ffff55' },
        { code: '&f', name: isHu ? 'Fehér' : 'White', hex: '#ffffff' },
    ];

    const mcStyles = [
        { code: '&k', name: isHu ? 'Mágikus' : 'Obfuscated', isMagic: true },
        { code: '&l', name: isHu ? 'Félkövér' : 'Bold', isMagic: false },
        { code: '&m', name: isHu ? 'Áthúzott' : 'Strikethrough', isMagic: false },
        { code: '&n', name: isHu ? 'Aláhúzott' : 'Underline', isMagic: false },
        { code: '&o', name: isHu ? 'Dőlt' : 'Italic', isMagic: false },
        { code: '&r', name: 'Reset', isMagic: false },
    ];

    useEffect(() => {
        if (!serverUuid) return;

        http.get(`/api/client/servers/${serverUuid}/files/contents?file=server.properties`)
            .then(({ data }) => {
                const match = data.match(/^motd=(.*)$/m);
                if (match && match[1]) {
                    setMotd(match[1]);
                }
            })
            .catch(() => {
                addFlash({
                    key: 'server-design',
                    type: 'error',
                    message: t.readErr,
                });
            })
            .finally(() => {
                setIsLoading(false);
            });
    }, [serverUuid]);

    const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file || !serverUuid) return;

        setIsUploading(true);
        clearFlashes('server-design');

        http.get(`/api/client/servers/${serverUuid}/files/upload`)
            .then(({ data }) => {
                const signedUrl = data.attributes.url;
                const formData = new FormData();
                formData.append('files', file, 'server-icon.png');

                return axios.post(`${signedUrl}&directory=/`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            })
            .then(() => {
                setPreviewIconUrl(URL.createObjectURL(file));
                addFlash({
                    key: 'server-design',
                    type: 'success',
                    message: t.uploadSuccess,
                });
            })
            .catch((err) => {
                addFlash({
                    key: 'server-design',
                    type: 'error',
                    message: t.uploadErr + (err.response?.data?.message || err.message),
                });
            })
            .finally(() => {
                setIsUploading(false);
            });
    };

    const insertCode = (code: string) => {
        const input = inputRef.current;
        if (!input) return;

        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const text = input.value;
        const replacement = text.substring(0, start) + code + text.substring(end);

        setMotd(replacement);

        setTimeout(() => {
            input.focus();
            input.setSelectionRange(start + code.length, start + code.length);
        }, 10);
    };

    const pregMatchMotd = (content: string) => /^motd=.*$/m.test(content);

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!serverUuid) return;

        setIsSubmitting(true);
        clearFlashes('server-design');

        http.get(`/api/client/servers/${serverUuid}/files/contents?file=server.properties`)
            .then(({ data }) => {
                let updatedContent = data;
                if (pregMatchMotd(updatedContent)) {
                    updatedContent = updatedContent.replace(/^motd=.*$/m, `motd=${motd}`);
                } else {
                    updatedContent += `\nmotd=${motd}`;
                }

                return http.post(
                    `/api/client/servers/${serverUuid}/files/write?file=server.properties`,
                    updatedContent,
                    { headers: { 'Content-Type': 'text/plain' } }
                );
            })
            .then(() => {
                addFlash({
                    key: 'server-design',
                    type: 'success',
                    message: t.saveSuccess,
                });
            })
            .catch((err) => {
                addFlash({
                    key: 'server-design',
                    type: 'error',
                    message: t.saveErr + (err.response?.data?.message || err.message),
                });
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    };

    const parseMotdToHtml = (text: string) => {
        if (!text) return <span className="text-neutral-500">{t.defaultMotd}</span>;

        const colorMap: { [key: string]: string } = {
            '0': '#000000', '1': '#0000aa', '2': '#00aa00', '3': '#00aaaa',
            '4': '#aa0000', '5': '#aa00aa', '6': '#ffaa00', '7': '#aaaaaa',
            '8': '#555555', '9': '#5555ff', 'a': '#55ff55', 'b': '#55ffff',
            'c': '#ff5555', 'd': '#ff55ff', 'e': '#ffff55', 'f': '#ffffff',
        };

        const parts = text.split(/&([0-9a-fA-fk-orR])/g);
        let currentStyle: React.CSSProperties = { color: '#ffffff', fontWeight: 'normal', textDecoration: 'none', fontStyle: 'normal' };
        let isMagicActive = false;

        return parts.map((part, index) => {
            if (index % 2 === 1) {
                const code = part.toLowerCase();
                if (colorMap[code]) {
                    currentStyle = { ...currentStyle, color: colorMap[code] };
                } else if (code === 'l') {
                    currentStyle = { ...currentStyle, fontWeight: 'bold' };
                } else if (code === 'm') {
                    currentStyle = { ...currentStyle, textDecoration: 'line-through' };
                } else if (code === 'n') {
                    currentStyle = { ...currentStyle, textDecoration: 'underline' };
                } else if (code === 'o') {
                    currentStyle = { ...currentStyle, fontStyle: 'italic' };
                } else if (code === 'k') {
                    isMagicActive = true;
                } else if (code === 'r') {
                    currentStyle = { color: '#ffffff', fontWeight: 'normal', textDecoration: 'none', fontStyle: 'normal' };
                    isMagicActive = false;
                }
                return null;
            }

            if (!part) return null;

            if (isMagicActive) {
                return (
                    <span key={index} style={currentStyle}>
                        {part.split('').map((char, charIdx) => (
                            <span key={charIdx} style={{ display: 'inline-block', fontFamily: 'monospace' }}>
                                {String.fromCharCode(33 + Math.floor(Math.random() * 60))}
                            </span>
                        ))}
                    </span>
                );
            }

            return <span key={index} style={currentStyle}>{part}</span>;
        });
    };

    return (
        <ServerContentBlock title={t.pageTitle}>
            <FlashMessageRender byKey={'server-design'} css={tw`mb-4`} />

            {isLoading ? (
                <Spinner size={'large'} centered />
            ) : (
                <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-6 max-w-[1200px] mx-auto`}>
                    <div css={tw`md:col-span-2 space-y-6`}>
                        <form onSubmit={handleFormSubmit}>
                            <TitledGreyBox title={t.boxTitle}>
                                <div css={tw`space-y-5`}>
                                    <div>
                                        <label css={tw`text-xs font-semibold uppercase text-neutral-400 block mb-2`}>
                                            {t.colorsLabel}
                                        </label>

                                        <div css={tw`flex flex-wrap gap-1.5 p-2 bg-neutral-900 border border-neutral-800 rounded-md`}>
                                            {mcColors.map((color) => (
                                                <button
                                                    key={color.code}
                                                    type="button"
                                                    onClick={() => insertCode(color.code)}
                                                    style={{ backgroundColor: color.hex }}
                                                    title={`${color.name} (${color.code})`}
                                                    css={tw`w-7 h-7 rounded border border-neutral-700 hover:scale-110 transition-transform cursor-pointer flex items-center justify-center`}
                                                >
                                                    <span style={{ color: color.code === '&0' ? '#fff' : '#000', textShadow: '0 0 2px rgba(255,255,255,0.8)', fontSize: '10px', fontWeight: 'bold' }}>
                                                        {color.code.replace('&', '')}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>

                                        <div css={tw`flex flex-wrap gap-1.5 mt-2 p-1.5 bg-neutral-900 border border-neutral-800 rounded-md`}>
                                            {mcStyles.map((style) => (
                                                <button
                                                    key={style.code}
                                                    type="button"
                                                    onClick={() => insertCode(style.code)}
                                                    style={style.isMagic ? { backgroundColor: '#aa00aa', borderColor: '#aa00aa', color: '#ffffff', fontWeight: 'bold' } : {}}
                                                    css={tw`px-2.5 py-1 bg-neutral-800 border border-neutral-700 hover:bg-neutral-700 rounded text-xs font-medium text-neutral-200 transition-all cursor-pointer`}
                                                >
                                                    {style.name} ({style.code})
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div>
                                        <label css={tw`text-xs font-semibold uppercase text-neutral-400 block mb-2`}>
                                            {t.motdLabel}
                                        </label>
                                        <input
                                            ref={inputRef}
                                            type="text"
                                            value={motd}
                                            onChange={(e) => setMotd(e.target.value)}
                                            placeholder={t.motdPlaceholder}
                                            css={tw`w-full bg-neutral-800 border border-neutral-700 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-cyan-500 font-mono`}
                                        />
                                    </div>

                                    <div>
                                        <label css={tw`text-xs font-semibold uppercase text-neutral-400 block mb-2`}>
                                            {t.iconLabel}
                                        </label>

                                        <div css={tw`flex flex-col sm:flex-row gap-4 items-start sm:items-center p-4 bg-neutral-900 border border-neutral-800 rounded-md`}>
                                            <div css={tw`flex-1 w-full`}>
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/png"
                                                    onChange={handleFileUpload}
                                                    disabled={isUploading}
                                                    css={tw`text-xs text-neutral-300`}
                                                />
                                                <p css={tw`text-xs text-neutral-500 mt-1`}>{t.iconHint}</p>
                                                {isUploading && <p css={tw`text-xs text-cyan-400 mt-1`}>{t.uploading}</p>}
                                            </div>
                                        </div>
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

                    <div>
                        <TitledGreyBox title={t.previewTitle}>
                            <div css={tw`flex items-center gap-3 p-3 bg-neutral-900 border border-neutral-800 rounded-md`}>
                                <div css={tw`w-16 h-16 bg-neutral-800 rounded flex items-center justify-center overflow-hidden flex-shrink-0 border border-neutral-700`}>
                                    {previewIconUrl ? (
                                        <img src={previewIconUrl} alt="Server Icon" css={tw`w-full h-full object-cover`} />
                                    ) : (
                                        <span css={tw`text-xs text-neutral-500 font-mono`}>64×64</span>
                                    )}
                                </div>
                                <div css={tw`min-w-0 flex-1`}>
                                    <div css={tw`flex justify-between items-center mb-1`}>
                                        <span css={tw`text-sm font-bold text-white truncate`}>{serverName}</span>
                                        <span css={tw`text-xs font-mono text-neutral-400`}>0/20</span>
                                    </div>
                                    <div css={tw`text-xs font-mono leading-snug break-words`}>
                                        {parseMotdToHtml(motd)}
                                    </div>
                                </div>
                            </div>
                        </TitledGreyBox>
                    </div>
                </div>
            )}
        </ServerContentBlock>
    );
};

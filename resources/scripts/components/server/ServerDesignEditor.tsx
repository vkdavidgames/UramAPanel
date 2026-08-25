import React, { useState, useEffect, useRef } from 'react';
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
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name) || 'Minecraft Szerver';
    const serverUuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    
    const { addFlash, clearFlashes } = useFlash();
    const inputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    
    const [motd, setMotd] = useState('');
    const [serverIcon, setServerIcon] = useState('default');
    const [previewIconUrl, setPreviewIconUrl] = useState('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isUploading, setIsUploading] = useState(false);

    const mcColors = [
        { code: '&0', name: 'Fekete', hex: '#000000' },
        { code: '&1', name: 'Sötétkék', hex: '#0000aa' },
        { code: '&2', name: 'Sötétzöld', hex: '#00aa00' },
        { code: '&3', name: 'Sötétlila', hex: '#00aaaa' },
        { code: '&4', name: 'Sötétvörös', hex: '#aa0000' },
        { code: '&5', name: 'Lila', hex: '#aa00aa' },
        { code: '&6', name: 'Arany', hex: '#ffaa00' },
        { code: '&7', name: 'Szürke', hex: '#aaaaaa' },
        { code: '&8', name: 'Sötétszürke', hex: '#555555' },
        { code: '&9', name: 'Kék', hex: '#5555ff' },
        { code: '&a', name: 'Zöld', hex: '#55ff55' },
        { code: '&b', name: 'Aqua', hex: '#55ffff' },
        { code: '&c', name: 'Vörös', hex: '#ff5555' },
        { code: '&d', name: 'Rózsaszín', hex: '#ff55ff' },
        { code: '&e', name: 'Sárga', hex: '#ffff55' },
        { code: '&f', name: 'Fehér', hex: '#ffffff' },
    ];

    const mcStyles = [
        { code: '&k', name: 'Mágikus', isMagic: true },
        { code: '&l', name: 'Félkövér', isMagic: false },
        { code: '&m', name: 'Áthúzott', isMagic: false },
        { code: '&n', name: 'Aláhúzott', isMagic: false },
        { code: '&o', name: 'Dőlt', isMagic: false },
        { code: '&r', name: 'Reset', isMagic: false },
    ];

    useEffect(() => {
        if (!serverUuid) return;
        
        http.get(`/auth/design_backend.php?server_uuid=${serverUuid}`)
            .then(({ data }) => {
                if (data && data.status === 'success') {
                    setMotd(data.motd || '');
                    setServerIcon(data.icon || 'default');
                } else {
                    addFlash({ key: 'server-design', type: 'error', message: data.message || 'Nem sikerült betölteni a beállításokat.' });
                }
            })
            .catch((err) => {
                const msg = err.response?.data?.message || 'Hiba történt az adatok lekérése során.';
                addFlash({ key: 'server-design', type: 'error', message: msg });
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

        const formData = new FormData();
        formData.append('server_uuid', String(serverUuid));
        formData.append('icon_file', file);

        http.post('/auth/design_backend.php', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        .then(({ data }) => {
            if (data && data.status === 'success') {
                setServerIcon('custom');
                setPreviewIconUrl(URL.createObjectURL(file));
                addFlash({ key: 'server-design', type: 'success', message: data.message });
            } else {
                addFlash({ key: 'server-design', type: 'error', message: data.message || 'Hiba a feltöltés közben.' });
            }
        })
        .catch((err) => {
            const msg = err.response?.data?.message || 'Hálózati hiba történt a feltöltéskor.';
            addFlash({ key: 'server-design', type: 'error', message: msg });
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

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        clearFlashes('server-design');

        http.post('/auth/design_backend.php', {
            server_uuid: serverUuid,
            motd: motd,
            icon: serverIcon
        })
        .then(({ data }) => {
            if (data && data.status === 'success') {
                addFlash({ key: 'server-design', type: 'success', message: data.message });
            } else {
                addFlash({ key: 'server-design', type: 'error', message: data.message || 'Hiba történt a mentéskor.' });
            }
        })
        .catch((err) => {
            const msg = err.response?.data?.message || 'Hiba történt a mentés során.';
            addFlash({ key: 'server-design', type: 'error', message: msg });
        })
        .finally(() => {
            setIsSubmitting(false);
        });
    };

    const parseMotdToHtml = (text: string) => {
        if (!text) return <span className="text-neutral-500">A DavidGames Minecraft Szervere</span>;
        
        const colorMap: { [key: string]: string } = {
            '0': '#000000', '1': '#0000aa', '2': '#00aa00', '3': '#00aaaa',
            '4': '#aa0000', '5': '#aa00aa', '6': '#ffaa00', '7': '#aaaaaa',
            '8': '#555555', '9': '#5555ff', 'a': '#55ff55', 'b': '#55ffff',
            'c': '#ff5555', 'd': '#ff55ff', 'e': '#ffff55', 'f': '#ffffff'
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
        <ServerContentBlock title={'Server Design'}>
            <FlashMessageRender byKey={'server-design'} css={tw`mb-4`} />

            {isLoading ? (
                <Spinner size={'large'} centered />
            ) : (
                <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-6 max-w-[1200px] mx-auto`}>
                    
                    <div css={tw`md:col-span-2 space-y-6`}>
                        <form onSubmit={handleFormSubmit}>
                            <TitledGreyBox title={'Szerver Megjelenés és MOTD Panel'}>
                                <div css={tw`space-y-5`}>
                                    
                                    <div>
                                        <label css={tw`text-xs font-semibold uppercase text-neutral-400 block mb-2`}>
                                            Vizuális Minecraft Színválasztó Gombok
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
                                            Szerver Leírása (MOTD szöveg)
                                        </label>
                                        <input 
                                            ref={inputRef}
                                            type="text"
                                            value={motd}
                                            onChange={(e) => setMotd(e.target.value)}
                                            placeholder="&aDavidGames &f| &bSzerverünk &lONLINE"
                                            css={tw`w-full bg-neutral-800 border border-neutral-700 rounded-md text-neutral-200 p-3 text-sm focus:outline-none focus:border-cyan-500 font-mono`}
                                        />
                                    </div>

                                    <div>
                                        <label css={tw`text-xs font-semibold uppercase text-neutral-400 block mb-2`}>
                                            Szerver Ikon Kezelés
                                        </label>
                                        
                                        <div css={tw`flex flex-col sm:flex-row gap-4 items-start sm:items-center p-4 bg-neutral-900 border border-neutral-800 rounded-md`}>
                                            <div css={tw`flex-1`}>
                                                <select
                                                    value={serverIcon}
                                                    onChange={(e) => setServerIcon(e.target.value)}
                                                    css={tw`w-full bg-neutral-800 border border-neutral-700 rounded-md text-neutral-200 p-2 text-sm focus:outline-none focus:border-cyan-500 mb-3`}
                                                >
                                                    <option value="default">Gyári Központi DavidGames logó használata</option>
                                                    <option value="custom">Egyedi feltöltött ikon használata (server-icon.png)</option>
                                                </select>
                                                
                                                <input 
                                                    ref={fileInputRef}
                                                    type="file" 
                                                    accept="image/png, image/jpeg, image/jpg" 
                                                    onChange={handleFileUpload} 
                                                    css={tw`hidden`} 
                                                />
                                                
                                                <Button 
                                                    type="button" 
                                                    onClick={() => fileInputRef.current?.click()}
                                                    isLoading={isUploading}
                                                    disabled={isUploading}
                                                    css={tw`w-full text-xs`}
                                                >
                                                    {isUploading ? 'Ikon feldolgozása...' : 'Új Ikon Feltöltése (.png, .jpg)'}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <div css={tw`flex justify-end pt-2`}>
                                        <Button type="submit" size={'large'} isLoading={isSubmitting} disabled={isSubmitting}>
                                            Save Settings
                                        </Button>
                                    </div>
                                </div>
                            </TitledGreyBox>
                        </form>
                    </div>

                    <div css={tw`space-y-6`}>
                        <TitledGreyBox title={'Élő Szerverlista Előnézet'}>
                            <div 
                                style={{ 
                                    backgroundColor: '#1e1e1e',
                                    backgroundSize: '16px 16px',
                                    imageRendering: 'pixelated'
                                }}
                                css={tw`w-full rounded p-3 shadow-2xl relative border border-neutral-900`}
                            >
                                <div css={tw`bg-black bg-opacity-80 border border-neutral-900 rounded p-3 font-mono text-sm shadow-inner flex items-start space-x-3 select-none`}>
                                    
                                    <div css={tw`w-16 h-16 bg-neutral-900 border border-neutral-800 rounded flex items-center justify-center flex-shrink-0 overflow-hidden`}>
                                        {serverIcon === 'default' || !previewIconUrl ? (
                                            <div css={tw`w-full h-full bg-gradient-to-br from-cyan-900 to-neutral-900 flex items-center justify-center text-[10px] text-cyan-400 font-sans font-bold tracking-tighter text-center uppercase`}>
                                                DG LOGO
                                            </div>
                                        ) : (
                                            <img src={previewIconUrl} alt="Server Icon Preview" css={tw`w-full h-full object-cover`} />
                                        )}
                                    </div>

                                    <div css={tw`flex-1 min-w-0`}>
                                        <div css={tw`flex justify-between items-center mb-1`}>
                                            <span css={tw`text-neutral-200 font-bold truncate text-xs font-sans`}>{serverName}</span>
                                            <span css={tw`text-green-500 font-sans text-xs`}>📶 120/120</span>
                                        </div>
                                        <div css={tw`text-sm break-words whitespace-pre-wrap leading-tight text-neutral-200`}>
                                            {parseMotdToHtml(motd)}
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <span css={tw`text-[11px] text-neutral-500 mt-2 block text-center leading-tight`}>
                                Így látják a játékosok a szervert a kliensükben!
                            </span>
                        </TitledGreyBox>
                    </div>

                </div>
            )}
        </ServerContentBlock>
    );
};

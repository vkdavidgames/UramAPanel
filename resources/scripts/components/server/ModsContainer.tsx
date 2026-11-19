import { useTranslation } from 'react-i18next';
import React, { useState, useEffect } from 'react';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import axios from 'axios';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Spinner from '@/components/elements/Spinner';

interface ExtensionProject {
    project_id: string;
    title: string;
    description: string;
    icon_url: string;
    latest_version: string;
    server_side: string;
    client_side: string;
    project_type: string;
}

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data);
    const { i18n } = useTranslation();
    const storeLang = useStoreState((state) => state.user.data?.language || '');
    const userLang = localStorage.getItem('selectedLanguage') || i18n.language || storeLang;
    const isHu = (userLang || (typeof navigator !== 'undefined' ? navigator.language : 'en'))
        .toLowerCase()
        .startsWith('hu');

    const t = {
        pageTitle: isHu ? 'Bővítmények' : 'Extension Manager',
        subtitle: isHu
            ? 'Bővítsd a szervert Modokkal és Pluginokkal egyetlen kattintással.'
            : 'Extend your server with Mods and Plugins in a single click.',
        modsTab: isHu ? '📦 Modok (/mods)' : '📦 Mods (/mods)',
        pluginsTab: isHu ? '🔌 Pluginok (/plugins)' : '🔌 Plugins (/plugins)',
        searchModPlaceholder: isHu ? 'Mod keresése (pl. Create, Valkyrien)...' : 'Search mods (e.g. Create, Valkyrien)...',
        searchPluginPlaceholder: isHu ? 'Plugin keresése (pl. EssentialsX, WorldEdit)...' : 'Search plugins (e.g. EssentialsX, WorldEdit)...',
        versionPlaceholder: isHu ? 'Verzió (pl. 1.21.1)' : 'Version (e.g. 1.21.1)',
        searchBtn: isHu ? 'Keresés' : 'Search',
        searchApiErr: isHu
            ? 'Hálózati hiba történt a Modrinth API elérése közben.'
            : 'A network error occurred while reaching the Modrinth API.',
        downloadingDep: isHu ? 'Függőség letöltése...' : 'Downloading dependency...',
        searchingDep: (id: string) => (isHu ? `Függőség keresése: ${id}...` : `Searching dependency: ${id}...`),
        clientOnlyWarn: (title: string) =>
            isHu
                ? `⚠️ FIGYELEM!\n\nA(z) "${title}" mod KIZÁRÓLAG KLIENS-OLDALI (Client-Only)!\n\nHa feltöltöd a szerver /mods mappájába, a szerver valószínűleg össze fog omlani!\n\nBiztosan megpróbálod a telepítést?`
                : `⚠️ WARNING!\n\nThe mod "${title}" is CLIENT-ONLY!\n\nUploading it to the server's /mods folder will likely crash the server!\n\nAre you sure you want to try installing it anyway?`,
        fetchingVersion: (title: string) => (isHu ? `Verzió lekérdezése: ${title}...` : `Fetching version: ${title}...`),
        noCompatible: (title: string, ver: string) =>
            isHu
                ? `Nem található kompatibilis kiadás a(z) ${title} elemhez ezen a verzión: ${ver}`
                : `No compatible release found for ${title} on version: ${ver}`,
        downloadingMain: (title: string, dir: string) =>
            isHu ? `Letöltés folyamatban: ${title} -> /${dir}...` : `Downloading: ${title} -> /${dir}...`,
        checkingDeps: isHu ? 'Függőségek ellenőrzése és telepítése...' : 'Checking and installing dependencies...',
        installSuccess: (title: string, dir: string, file: string | boolean) =>
            isHu
                ? `✅ Sikeresen telepítve: ${title}\nCélmappa: /${dir}\nFájlnév: ${file}`
                : `✅ Successfully installed: ${title}\nTarget folder: /${dir}\nFilename: ${file}`,
        panelErrPrefix: isHu ? 'Hiba a Pterodactyl válaszban: ' : 'Pterodactyl API error: ',
        downloadErr: isHu
            ? 'Hiba történt a letöltés során! Ellenőrizd a mappa jogosultságokat vagy a konzolt.'
            : 'An error occurred during download! Check folder permissions or the console.',
        installingBtn: isHu ? 'Telepítés...' : 'Installing...',
        installBtn: (dir: string) => (isHu ? `Telepítés (/${dir})` : `Install (/${dir})`),
    };

    const [mode, setMode] = useState<'mod' | 'plugin'>('mod');
    const [searchQuery, setSearchQuery] = useState('');
    const [modLoader, setModLoader] = useState('neoforge');
    const [mcVersion, setMcVersion] = useState('1.21.1');
    const [loading, setLoading] = useState(false);
    const [extensions, setExtensions] = useState<ExtensionProject[]>([]);
    const [installing, setInstalling] = useState<string | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);

    useEffect(() => {
        if (server) {
            const versionVar = server.variables.find(
                (v) => v.envVariable === 'MINECRAFT_VERSION' || v.envVariable === 'SERVER_VERSION' || v.envVariable === 'VERSION'
            );
            if (versionVar && versionVar.serverValue) {
                const match = versionVar.serverValue.match(/^[0-9]+\.[0-9]+(\.[0-9]+)?/);
                if (match) {
                    setMcVersion(match[0]);
                }
            }

            const loaderVar = server.variables.find(
                (v) => v.envVariable === 'MOD_LOADER' || v.envVariable === 'TYPE' || v.envVariable === 'SERVER_TYPE'
            );
            if (loaderVar && loaderVar.serverValue) {
                const val = loaderVar.serverValue.toLowerCase();
                if (val.includes('fabric')) setModLoader('fabric');
                else if (val.includes('neoforge')) setModLoader('neoforge');
                else if (val.includes('forge')) setModLoader('forge');
                else if (val.includes('quilt')) setModLoader('quilt');
                else if (val.includes('paper') || val.includes('spigot') || val.includes('purpur')) setMode('plugin');
            }
        }
    }, [server]);

    const searchExtensions = async () => {
        if (!searchQuery) return;
        setLoading(true);
        setStatusMessage(null);
        try {
            const projectTypeFilter = mode === 'plugin' ? `"project_type:plugin"` : `"project_type:mod"`;
            const loaderFilter = mode === 'mod' ? `["categories:${modLoader}"],` : '';
            const versionFilter = mcVersion.trim() ? `["versions:${mcVersion.trim()}"],` : '';

            const facets = `[${loaderFilter}${versionFilter}[${projectTypeFilter}]]`;

            const response = await axios.get(`https://modrinth.com`, {
                params: {
                    query: searchQuery,
                    facets: facets,
                    limit: 12,
                },
            });

            const results = response.data.hits.map((hit: any) => ({
                project_id: hit.project_id,
                title: hit.title,
                description: hit.description,
                icon_url: hit.icon_url || 'https://modrinth.com',
                latest_version: hit.latest_version,
                server_side: hit.server_side || 'unknown',
                client_side: hit.client_side || 'unknown',
                project_type: hit.project_type || mode,
            }));

            setExtensions(results);
        } catch (error) {
            console.error('Search error:', error);
            alert(t.searchApiErr);
        } finally {
            setLoading(false);
        }
    };

    const downloadVersionFile = async (projectId: string, versionData: any, targetDir: string) => {
        if (!server || !versionData.files || versionData.files.length === 0) return false;

        const fileObj = versionData.files.find((f: any) => f.primary) || versionData.files[0];
        const realFileName = fileObj.filename;
        const actualVersionId = versionData.id;

        const safeEncodedFileName = encodeURIComponent(realFileName);
        const directCdnUrl = `https://modrinth.com{projectId}/versions/${actualVersionId}/${safeEncodedFileName}?mr_download_reason=standalone`;

        await axios.post(`/api/client/servers/${server.uuid}/files/pull`, {
            url: directCdnUrl,
            directory: targetDir,
            filename: realFileName,
        });

        return realFileName;
    };

    const processDependencies = async (dependencies: any[], targetDir: string) => {
        if (!dependencies || dependencies.length === 0) return;

        for (const dep of dependencies) {
            if (dep.dependency_type === 'required' && dep.version_id) {
                try {
                    setStatusMessage(t.downloadingDep);
                    const depVersionRes = await axios.get(`https://modrinth.com{dep.version_id}`);
                    if (depVersionRes.data) {
                        await downloadVersionFile(depVersionRes.data.project_id, depVersionRes.data, targetDir);
                    }
                } catch (e) {
                    console.warn(`Failed dependency download: ${dep.version_id}`, e);
                }
            } else if (dep.dependency_type === 'required' && dep.project_id) {
                try {
                    setStatusMessage(t.searchingDep(dep.project_id));
                    const loadersParam = mode === 'mod' ? `&loaders=${encodeURIComponent(JSON.stringify([modLoader]))}` : '';
                    const versionStr = mcVersion.trim() ? `&game_versions=${encodeURIComponent(JSON.stringify([mcVersion.trim()]))}` : '';
                    const targetApiUrl = `https://api.modrinth.com/v2/project/${dep.project_id}/version?${loadersParam}${versionStr}`;

                    const depVersionRes = await axios.get(targetApiUrl);
                    if (depVersionRes.data && depVersionRes.data.length > 0) {
                        await downloadVersionFile(dep.project_id, depVersionRes.data[0], targetDir);
                    }
                } catch (e) {
                    console.warn(`Failed project dependency download: ${dep.project_id}`, e);
                }
            }
        }
    };

    const installExtension = async (ext: ExtensionProject) => {
        if (!server) return;

        const targetDirectory = mode === 'plugin' ? 'plugins' : 'mods';

        if (mode === 'mod' && ext.server_side === 'unsupported') {
            const confirmInstall = window.confirm(t.clientOnlyWarn(ext.title));
            if (!confirmInstall) return;
        }

        setInstalling(ext.project_id);
        setStatusMessage(t.fetchingVersion(ext.title));

        try {
            const loadersParam = mode === 'mod' ? `&loaders=${encodeURIComponent(JSON.stringify([modLoader]))}` : '';
            const versionStr = mcVersion.trim() ? `&game_versions=${encodeURIComponent(JSON.stringify([mcVersion.trim()]))}` : '';
            const targetApiUrl = `https://api.modrinth.com/v2/project/${ext.project_id}/version?include_changelog=false${loadersParam}${versionStr}`;

            const response = await axios.get(targetApiUrl);

            if (!response || !response.data || !Array.isArray(response.data) || response.data.length === 0) {
                alert(t.noCompatible(ext.title, mcVersion));
                setInstalling(null);
                setStatusMessage(null);
                return;
            }

            const targetVersion = response.data[0];

            setStatusMessage(t.downloadingMain(ext.title, targetDirectory));
            const downloadedName = await downloadVersionFile(ext.project_id, targetVersion, targetDirectory);

            if (targetVersion.dependencies && targetVersion.dependencies.length > 0) {
                setStatusMessage(t.checkingDeps);
                await processDependencies(targetVersion.dependencies, targetDirectory);
            }

            alert(t.installSuccess(ext.title, targetDirectory, downloadedName));
        } catch (error: any) {
            console.error('Install error:', error);
            if (error?.response?.data?.errors) {
                alert(t.panelErrPrefix + JSON.stringify(error.response.data.errors));
            } else {
                alert(t.downloadErr);
            }
        } finally {
            setInstalling(null);
            setStatusMessage(null);
        }
    };

    const targetDirName = mode === 'plugin' ? 'plugins' : 'mods';

    return (
        <ServerContentBlock title={t.pageTitle}>
            <div className="bg-neutral-900/80 p-6 rounded-xl border border-neutral-800 shadow-md">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-100">{t.pageTitle}</h1>
                        <p className="text-xs text-neutral-400 mt-1">{t.subtitle}</p>
                    </div>

                    <div className="flex bg-neutral-800 p-1 rounded-lg border border-neutral-700">
                        <button
                            onClick={() => { setMode('mod'); setExtensions([]); }}
                            className={`px-4 py-1.5 rounded-md text-sm font-semibold transition-all ${
                                mode === 'mod'
                                    ? 'bg-indigo-600 text-white shadow'
                                    : 'text-neutral-400 hover:text-neutral-200'
                            }`}
                        >
                            {t.modsTab}
                        </button>
                        <button
                            onClick={() => { setMode('plugin'); setExtensions([]); }}
                            className={`px-4 py-1.5 rounded-md text-sm font-semibold transition-all ${
                                mode === 'plugin'
                                    ? 'bg-indigo-600 text-white shadow'
                                    : 'text-neutral-400 hover:text-neutral-200'
                            }`}
                        >
                            {t.pluginsTab}
                        </button>
                    </div>
                </div>

                {statusMessage && (
                    <div className="mb-4 text-xs text-yellow-400 bg-neutral-800 p-2.5 rounded border border-yellow-500/30 animate-pulse text-center">
                        {statusMessage}
                    </div>
                )}

                <div className="flex flex-col md:flex-row gap-4 mb-8">
                    <div className="flex-1">
                        <Input
                            type="text"
                            placeholder={mode === 'mod' ? t.searchModPlaceholder : t.searchPluginPlaceholder}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.currentTarget.value)}
                            onKeyPress={(e) => e.key === 'Enter' && searchExtensions()}
                        />
                    </div>

                    {mode === 'mod' && (
                        <div className="w-full md:w-36">
                            <Select value={modLoader} onChange={(e) => setModLoader(e.target.value)}>
                                <option value="neoforge">NeoForge</option>
                                <option value="forge">Forge</option>
                                <option value="fabric">Fabric</option>
                                <option value="quilt">Quilt</option>
                            </Select>
                        </div>
                    )}

                    <div className="w-full md:w-36">
                        <Input
                            type="text"
                            placeholder={t.versionPlaceholder}
                            value={mcVersion}
                            onChange={(e) => setMcVersion(e.target.value)}
                            onKeyPress={(e) => e.key === 'Enter' && searchExtensions()}
                        />
                    </div>

                    <Button onClick={searchExtensions} className="w-full md:w-auto">
                        {t.searchBtn}
                    </Button>
                </div>

                {loading ? (
                    <div className="flex justify-center my-12"><Spinner /></div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {extensions.map((ext) => (
                            <div key={ext.project_id} className="flex gap-4 p-4 bg-neutral-800/80 rounded-lg border border-neutral-700 items-start relative overflow-hidden">
                                <img src={ext.icon_url} alt={ext.title} className="w-16 h-16 rounded-md object-cover flex-shrink-0" />
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <h3 className="text-lg font-bold text-neutral-100 truncate">{ext.title}</h3>

                                        {mode === 'mod' && ext.server_side === 'unsupported' && (
                                            <span className="text-[10px] bg-red-900/80 text-red-200 border border-red-500/50 px-1.5 py-0.5 rounded uppercase font-semibold">
                                                Client Only!
                                            </span>
                                        )}
                                        <span className="text-[10px] bg-neutral-700 text-neutral-300 px-1.5 py-0.5 rounded uppercase font-semibold">
                                            {mode === 'plugin' ? 'Plugin' : ext.server_side}
                                        </span>
                                    </div>

                                    <p className="text-sm text-neutral-400 line-clamp-2 mb-3">{ext.description}</p>

                                    <Button
                                        isSecondary={ext.server_side !== 'unsupported'}
                                        isLoading={installing === ext.project_id}
                                        onClick={() => installExtension(ext)}
                                        className={`text-xs py-1 px-3 ${ext.server_side === 'unsupported' && mode === 'mod' ? 'bg-red-800 hover:bg-red-700 text-white' : ''}`}
                                    >
                                        {installing === ext.project_id ? t.installingBtn : t.installBtn(targetDirName)}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </ServerContentBlock>
    );
};

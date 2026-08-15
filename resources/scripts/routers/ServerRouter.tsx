import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { useRouteMatch, NavLink, Route, Switch as RouterSwitch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import { CSSTransition } from 'react-transition-group';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import SubNavigation from '@/components/elements/SubNavigation';
import InstallListener from '@/components/server/InstallListener';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';

// GYÁRI ALAPALDALAK IMPORTOKRA VALÓ HIVATKOZÁSAI
import ServerConsoleContainer from '@/components/server/console/ServerConsoleContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import FileEditContainer from '@/components/server/files/FileEditContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import ScheduleEditContainer from '@/components/server/schedules/ScheduleEditContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import StartupContainer from '@/components/server/startup/StartupContainer';
import SettingsContainer from '@/components/server/settings/SettingsContainer';

// EGYEDI RECOVERED COMPONENT IMPORTOK
import ConfigEditor from '@/components/server/ConfigEditor';
import ModsContainer from '@/components/server/ModsContainer';
import ServerDesignEditor from '@/components/server/ServerDesignEditor';
import PlayerManager from '@/components/server/PlayerManager';

export default () => {
    const match = useRouteMatch<{ id: string }>();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

    return (
        <React.Fragment key={'server-router'}>
            <NavigationBar />
            {!uuid || !id ? (
                error ? (
                    <ServerError message={error} />
                ) : (
                    <Spinner size={'large'} centered />
                )
            ) : (
                <>
                    <CSSTransition timeout={150} classNames={'fade'} appear in>
                        <SubNavigation id={'SubNavigation'}>
                            <div>
                                {/* Hivatalos Gyári Pterodactyl Alapgombok */}
                                <NavLink to={`/server/${match.params.id}`} exact>Console</NavLink>
                                <NavLink to={`/server/${match.params.id}/files`}>File Manager</NavLink>
                                <NavLink to={`/server/${match.params.id}/databases`}>Databases</NavLink>
                                <NavLink to={`/server/${match.params.id}/schedules`}>Schedules</NavLink>
                                <NavLink to={`/server/${match.params.id}/users`}>Users</NavLink>
                                <NavLink to={`/server/${match.params.id}/backups`}>Backups</NavLink>
                                <NavLink to={`/server/${match.params.id}/network`}>Network</NavLink>
                                <NavLink to={`/server/${match.params.id}/startup`}>Startup</NavLink>
                                <NavLink to={`/server/${match.params.id}/settings`}>Settings</NavLink>

                                {/* A Mi Helyreállított Egyedi Gombjaink */}
                                <NavLink to={`/server/${match.params.id}/extensions`} exact>Extension Manager</NavLink>
                                <NavLink to={`/server/${match.params.id}/config`} exact>Config Editor</NavLink>
                                <NavLink to={`/server/${match.params.id}/design`} exact>Server Design</NavLink>
                                <NavLink to={`/server/${match.params.id}/players`} exact>Player Manager</NavLink>

                                {rootAdmin && (
                                    <a href={`/admin/servers/view/${serverId}`} target={'_blank'} rel="noreferrer">
                                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                                    </a>
                                )}
                            </div>
                        </SubNavigation>
                    </CSSTransition>
                    <InstallListener />
                    <TransferListener />
                    <WebsocketHandler />
                    
                    {/* BELSŐ REAT ÚTVONALAK REGISZTRÁCIÓJA (GYÁRI + SAJÁT) */}
                    <RouterSwitch>
                        <Route path={`${match.path}`} exact><ServerConsoleContainer /></Route>
                        <Route path={`${match.path}/files`} exact><FileManagerContainer /></Route>
                        <Route path={`${match.path}/files/edit/:file`} exact><FileEditContainer /></Route>
                        <Route path={`${match.path}/databases`} exact><DatabasesContainer /></Route>
                        <Route path={`${match.path}/schedules`} exact><ScheduleContainer /></Route>
                        <Route path={`${match.path}/schedules/:id`} exact><ScheduleEditContainer /></Route>
                        <Route path={`${match.path}/users`} exact><UsersContainer /></Route>
                        <Route path={`${match.path}/backups`} exact><BackupContainer /></Route>
                        <Route path={`${match.path}/network`} exact><NetworkContainer /></Route>
                        <Route path={`${match.path}/startup`} exact><StartupContainer /></Route>
                        <Route path={`${match.path}/settings`} exact><SettingsContainer /></Route>
                        
                        {/* SAJÁT MODULOK ÚTVONALAI */}
                        <Route path={`${match.path}/extensions`} exact><ModsContainer /></Route>
                        <Route path={`${match.path}/config`} exact><ConfigEditor /></Route>
                        <Route path={`${match.path}/design`} exact><ServerDesignEditor /></Route>
                        <Route path={`${match.path}/players`} exact><PlayerManager /></Route>
                    </RouterSwitch>
                </>
            )}
        </React.Fragment>
    );
};       

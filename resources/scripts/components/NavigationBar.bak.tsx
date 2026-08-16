import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center h-full no-underline text-neutral-300 px-6 cursor-pointer transition-all duration-150`};

        &:active,
        &:hover {
            ${tw`text-neutral-100 bg-black`};
        }

        &:active,
        &:hover,
        &.active {
            box-shadow: inset 0 -2px ${theme`colors.cyan.600`.toString()};
        }
    }
`;

export default () => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <div className={'w-full bg-neutral-900 shadow-md overflow-x-auto'} id={'NavigationBar'}>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto w-full flex items-center h-[3.5rem] max-w-[1200px]'}>
                <div id={'logo'} className={'flex-1'}>
                    <Link
                        to={'/'}
                        className={
                            'text-2xl font-header font-medium px-4 no-underline text-neutral-200 hover:text-neutral-100 transition-colors duration-150'
                        }
                    >
                        {name}
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    
                    {/* ÖNELLÁTÓ IGÉNYLŐ GOMB ÉS IFRAME MODAL KONTÉNER */}
                    <div style={{ display: 'flex', alignItems: 'center', height: '100%' }}>
                        <button 
                            onClick={() => {
                                const m = document.getElementById('claim-modal');
                                if (m) m.style.display = 'flex';
                            }}
                            style={{ 
                                color: '#10b981', 
                                fontWeight: 'bold', 
                                position: 'relative', 
                                zIndex: 99, 
                                pointerEvents: 'auto',
                                background: 'transparent',
                                border: 'none',
                                height: '100%',
                                cursor: 'pointer'
                            }}
                        >
                            Szerver Igénylés
                        </button>

                        <div id="claim-modal" style={{ display: 'none', position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh', backgroundColor: 'rgba(0,0,0,0.85)', zIndex: 99999, justifyContent: 'center', alignItems: 'center', pointerEvents: 'auto' }}>
                            <div style={{ position: 'relative', width: '100%', maxWidth: '420px', background: '#141417', borderRadius: '12px', padding: '10px', border: '1px solid #27272a', boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5)' }}>
                                <button 
                                    onClick={(e) => { 
                                        e.stopPropagation();
                                        const m = document.getElementById('claim-modal'); 
                                        if (m) m.style.display = 'none'; 
                                    }} 
                                    style={{ position: 'absolute', top: '15px', right: '15px', background: 'none', border: 'none', color: '#ef4444', fontWeight: 'bold', cursor: 'pointer', fontSize: '1.2rem', zIndex: 100000 }}
                                >
                                    ✕
                                </button>
                                <iframe src="/auth/claim.php" style={{ width: '100%', height: '650px', border: 'none', borderRadius: '8px', backgroundColor: 'transparent' }}></iframe>
                            </div>
                        </div>
                    </div>

                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact id={'NavigationDashboard'}>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={'Admin'}>
                            <a href={'/admin'} rel={'noreferrer'} id={'NavigationAdmin'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={'Account Settings'}>
                        <NavLink to={'/account'} id={'NavigationAccount'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}>
                        <button onClick={onTriggerLogout} id={'NavigationLogout'}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </div>
    );
};

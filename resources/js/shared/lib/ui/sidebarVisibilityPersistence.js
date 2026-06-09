const YAFFA_SESSION_STORAGE_KEY = 'YAFFA';

const readYaffaSessionState = () => {
    try {
        const serializedState = window.sessionStorage.getItem(YAFFA_SESSION_STORAGE_KEY);
        if (!serializedState) {
            return {};
        }

        const parsedState = JSON.parse(serializedState);

        return parsedState && typeof parsedState === 'object' ? parsedState : {};
    } catch {
        return {};
    }
};

const writeYaffaSessionState = (state) => {
    try {
        window.sessionStorage.setItem(YAFFA_SESSION_STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Ignore storage write failures (e.g. storage is unavailable).
    }
};

const setSidebarHiddenState = (isHidden) => {
    const currentState = readYaffaSessionState();
    const uiState = currentState.ui && typeof currentState.ui === 'object' ? currentState.ui : {};
    uiState.sidebarHidden = isHidden;
    currentState.ui = uiState;

    writeYaffaSessionState(currentState);
};

export const initializeSidebarVisibilityPersistence = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
        return;
    }

    const sessionState = readYaffaSessionState();
    const shouldHideSidebar = Boolean(sessionState.ui?.sidebarHidden);
    sidebar.classList.toggle('hide', shouldHideSidebar);

    const classObserver = new MutationObserver(() => {
        setSidebarHiddenState(sidebar.classList.contains('hide'));
    });

    classObserver.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class'],
    });
};

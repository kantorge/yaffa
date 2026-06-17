const STORAGE_KEY = 'yaffa-color-mode';

const getStoredMode = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'light';
    } catch {
        return 'light';
    }
};

const applyMode = (mode) => {
    document.documentElement.setAttribute('data-coreui-theme', mode);
    document.querySelectorAll('.color-mode-icon').forEach((icon) => {
        icon.classList.toggle('fa-sun', mode === 'dark');
        icon.classList.toggle('fa-moon', mode !== 'dark');
    });
    document.dispatchEvent(new CustomEvent('yaffa:colorModeChange', { detail: { mode } }));
};

const toggleMode = () => {
    const current = document.documentElement.getAttribute('data-coreui-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // localStorage unavailable (e.g. private browsing) — mode change applies for this session only
    }
    applyMode(next);
};

export const initializeColorMode = () => {
    applyMode(getStoredMode());

    document.querySelectorAll('[data-action="toggle-color-mode"]').forEach((btn) => {
        btn.addEventListener('click', toggleMode);
    });
};

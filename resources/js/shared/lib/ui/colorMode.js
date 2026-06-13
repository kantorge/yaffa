const STORAGE_KEY = 'yaffa-color-mode';

const getStoredMode = () => localStorage.getItem(STORAGE_KEY) || 'light';

const applyMode = (mode) => {
    document.documentElement.setAttribute('data-coreui-theme', mode);
    document.querySelectorAll('.color-mode-icon').forEach((icon) => {
        icon.className = icon.className.replace(/fa-sun|fa-moon/, mode === 'dark' ? 'fa-sun' : 'fa-moon');
    });
    document.dispatchEvent(new CustomEvent('yaffa:colorModeChange', { detail: { mode } }));
};

const toggleMode = () => {
    const current = document.documentElement.getAttribute('data-coreui-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem(STORAGE_KEY, next);
    applyMode(next);
};

export const initializeColorMode = () => {
    applyMode(getStoredMode());

    document.querySelectorAll('[data-action="toggle-color-mode"]').forEach((btn) => {
        btn.addEventListener('click', toggleMode);
    });
};

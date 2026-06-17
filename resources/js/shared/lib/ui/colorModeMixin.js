import { COLOR_MODE_EVENT } from './amchartsColorTheme';

/**
 * Adds a reactive `isDarkMode` boolean that tracks CoreUI's color mode.
 * Use it to pass `:is-dark="isDarkMode"` to v-calendar components.
 */
export const colorModeMixin = {
    data() {
        return {
            isDarkMode:
                document.documentElement.getAttribute('data-coreui-theme') ===
                'dark',
        };
    },
    mounted() {
        this._colorModeHandler = (event) => {
            this.isDarkMode = event.detail.mode === 'dark';
        };
        document.addEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
    },
    beforeUnmount() {
        document.removeEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
    },
};

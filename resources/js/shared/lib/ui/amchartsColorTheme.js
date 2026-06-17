import am4themes_dark from '@amcharts/amcharts4/themes/dark';

export const COLOR_MODE_EVENT = 'yaffa:colorModeChange';

export function applyAmChartsColorTheme(am4core) {
    const mode = document.documentElement.getAttribute('data-coreui-theme') || 'light';
    if (mode === 'dark') {
        am4core.useTheme(am4themes_dark);
    } else {
        am4core.unuseTheme(am4themes_dark);
    }
}

import { __ } from '@/shared/lib/i18n';

export const getLeftControlPanelToggleState = function (isCollapsed) {
    return {
        title: isCollapsed ? __('Expand left control panel') : __('Collapse left control panel'),
        iconClass: isCollapsed ? 'fas fa-angles-right' : 'fas fa-angles-left',
        ariaExpanded: String(!isCollapsed),
    };
};

export const applyLeftControlPanelToggleState = function (button, isCollapsed) {
    const state = getLeftControlPanelToggleState(isCollapsed);
    const icon = button.querySelector('[data-left-control-panel-toggle-icon]') ?? button.querySelector('i');

    if (icon) {
        icon.className = state.iconClass;
    }

    button.setAttribute('title', state.title);
    button.setAttribute('aria-label', state.title);
    button.setAttribute('aria-expanded', state.ariaExpanded);

    return state;
};

export const initializeTwoColumnLeftControlPanelToggle = function ({
    leftControlPanelSelector,
    mainContentSelector,
    toggleButtonSelector,
    expandedMainContentClass = 'col-lg-9',
    collapsedMainContentClass = 'col-lg-12',
}) {
    const leftControlPanel = document.querySelector(leftControlPanelSelector);
    const mainContent = document.querySelector(mainContentSelector);
    const toggleButton = document.querySelector(toggleButtonSelector);

    if (!leftControlPanel || !mainContent || !toggleButton) {
        return null;
    }

    const syncState = function (isCollapsed) {
        mainContent.classList.toggle(collapsedMainContentClass, isCollapsed);
        mainContent.classList.toggle(expandedMainContentClass, !isCollapsed);
        applyLeftControlPanelToggleState(toggleButton, isCollapsed);
    };

    syncState(leftControlPanel.classList.contains('d-none'));

    const onClick = function () {
        const isCollapsed = leftControlPanel.classList.toggle('d-none');
        syncState(isCollapsed);
    };

    toggleButton.addEventListener('click', onClick);

    return function cleanup() {
        toggleButton.removeEventListener('click', onClick);
    };
};
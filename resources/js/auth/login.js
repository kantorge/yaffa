const tooltipTriggerList = document.querySelectorAll(
    '[data-coreui-toggle="tooltip"]',
);
tooltipTriggerList.forEach(
    (tooltipTriggerEl) => new coreui.Tooltip(tooltipTriggerEl),
);

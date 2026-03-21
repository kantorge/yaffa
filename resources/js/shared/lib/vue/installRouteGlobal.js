export function installRouteGlobal(app) {
  app.config.globalProperties.route = window.route;
}

/*
 * The ONE place that says where the backend lives.
 * The frontend talks to the backend only through the JSON endpoints in backend/api/.
 * Frontend and backend must be on the same site (same origin) so the login cookie is sent along.
 * Default: the backend folder sits next to the frontend folder, like this:   .../eduwatch/frontend/   .../eduwatch/backend/
 */
window.EW_CONFIG = {
  API_BASE: '../backend/api'
};

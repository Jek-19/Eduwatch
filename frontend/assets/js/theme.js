/*
 * Loaded in <head> BEFORE the page is drawn, so dark mode does not flash white first.
 * The chosen theme is remembered in the browser (localStorage) and confirmed by the server after login (Settings).
 */
(function () {
  'use strict';
  var theme = 'light';
  try { theme = localStorage.getItem('ew_theme') === 'dark' ? 'dark' : 'light'; } catch (e) { /* storage blocked: stay light */ }
  document.documentElement.setAttribute('data-theme', theme);
})();

/*
 * EXT:wlp_prospi – Filterformular
 * - Einzelne aktive Filter (Chips) zurücksetzen und das Formular neu absenden.
 * - Nach dem Filtern zum Formular scrollen, damit Ergebnis und Filter im Blick bleiben.
 */
(function () {
  'use strict';

  var SCROLL_FLAG = 'wlpProspiScrollToFilter';

  function readScrollFlag() {
    try {
      var flag = window.sessionStorage.getItem(SCROLL_FLAG) === '1';
      window.sessionStorage.removeItem(SCROLL_FLAG);
      return flag;
    } catch (error) {
      // Web Storage kann durch Browsereinstellungen deaktiviert sein.
      return false;
    }
  }

  function writeScrollFlag() {
    try {
      window.sessionStorage.setItem(SCROLL_FLAG, '1');
    } catch (error) {
      // Das Zurücksetzen funktioniert auch ohne Web Storage.
    }
  }

  function findField(form, name) {
    var fields = form.querySelectorAll('[name="' + name + '"], [name$="[' + name + ']"]');
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].type !== 'hidden') {
        return fields[i];
      }
    }
    return null;
  }

  function resetField(form, name) {
    var field = findField(form, name);
    if (!field) {
      return;
    }
    if (field.type === 'checkbox' || field.type === 'radio') {
      field.checked = false;
    } else {
      field.value = '';
    }
    writeScrollFlag();
    form.submit();
  }

  function init(form) {
    var shouldScroll = readScrollFlag() || form.getAttribute('data-prospi-scroll') === 'true';
    if (shouldScroll) {
      window.addEventListener('load', function () {
        form.scrollIntoView({ behavior: 'auto', block: 'start' });
      });
    }

    form.addEventListener('click', function (event) {
      var button = event.target.closest('[data-prospi-reset]');
      if (!button || !form.contains(button)) {
        return;
      }
      event.preventDefault();
      resetField(form, button.getAttribute('data-prospi-reset'));
    });
  }

  var forms = document.querySelectorAll('[data-prospi-filter]');
  for (var i = 0; i < forms.length; i++) {
    init(forms[i]);
  }
})();

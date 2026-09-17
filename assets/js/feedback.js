(function () {
  'use strict';
  document.querySelectorAll('[data-kbms-feedback]').forEach(function (panel) {
    var status = panel.querySelector('[role="status"]');
    panel.querySelectorAll('button[data-helpful]').forEach(function (button) {
      button.addEventListener('click', function () {
        panel.querySelectorAll('button').forEach(function (item) { item.disabled = true; });
        fetch(kbmsFeedback.root + encodeURIComponent(panel.dataset.itemId) + '/feedback', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': kbmsFeedback.nonce },
          body: JSON.stringify({ helpful: button.dataset.helpful === 'true' })
        }).then(function (response) {
          if (!response.ok) { throw new Error('request failed'); }
          status.textContent = kbmsFeedback.success;
        }).catch(function () {
          status.textContent = kbmsFeedback.failure;
          panel.querySelectorAll('button').forEach(function (item) { item.disabled = false; });
        });
      });
    });
  });
}());

(function () {
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function init() {
    var drawer = document.getElementById('dp-menu');
    var trigger = document.querySelector('[data-dp-menu-trigger]');

    if (!drawer || !trigger) {
      return;
    }

    var panel = drawer.querySelector('.dp-menu__panel');
    var closeButton = drawer.querySelector('.dp-menu__close');
    var views = Array.prototype.slice.call(drawer.querySelectorAll('[data-dp-menu-view]'));
    var rootView = drawer.querySelector('[data-dp-menu-view]:not([data-dp-menu-parent])');

    function showView(view, moveFocus) {
      views.forEach(function (candidate) {
        candidate.hidden = candidate !== view;
      });

      if (!moveFocus) {
        return;
      }

      var first = view.querySelector(FOCUSABLE);

      if (first) {
        first.focus();
      }
    }

    function isOpen() {
      return !drawer.hidden;
    }

    function open() {
      showView(rootView, false);
      drawer.hidden = false;
      document.body.classList.add('dp-menu-is-open');
      trigger.setAttribute('aria-expanded', 'true');

      if (closeButton) {
        closeButton.focus();
      }
    }

    function close(returnFocus) {
      drawer.hidden = true;
      document.body.classList.remove('dp-menu-is-open');
      trigger.setAttribute('aria-expanded', 'false');
      showView(rootView, false);

      if (returnFocus) {
        trigger.focus();
      }
    }

    function trapFocus(event) {
      var items = Array.prototype.slice.call(panel.querySelectorAll(FOCUSABLE)).filter(function (item) {
        return item.offsetParent !== null;
      });

      if (!items.length) {
        return;
      }

      var first = items[0];
      var last = items[items.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    trigger.addEventListener('click', function () {
      if (isOpen()) {
        close(true);
      } else {
        open();
      }
    });

    drawer.addEventListener('click', function (event) {
      if (event.target.closest('[data-dp-menu-close]')) {
        close(true);

        return;
      }

      var opener = event.target.closest('[data-dp-menu-open]');

      if (opener) {
        var target = document.getElementById(opener.getAttribute('data-dp-menu-open'));

        if (target) {
          showView(target, true);
        }

        return;
      }

      if (event.target.closest('a[href]')) {
        close(false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (!isOpen()) {
        return;
      }

      if (event.key === 'Escape') {
        close(true);

        return;
      }

      if (event.key === 'Tab') {
        trapFocus(event);
      }
    });

    window.addEventListener('resize', function () {
      if (isOpen() && !trigger.offsetParent) {
        close(false);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

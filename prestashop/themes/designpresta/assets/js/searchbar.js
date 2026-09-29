(function ($) {
  if (!$) {
    return;
  }

  $(function () {
    var $widget = $('#search_widget');
    var instance = $widget.find('input[type="text"]').data('prestashop-psBlockSearchAutocomplete');

    if (!instance) {
      return;
    }

    var mobile = window.matchMedia('(max-width: 767px)');

    instance._resizeMenu = function () {
      var widget = $widget[0].getBoundingClientRect();
      var right = mobile.matches ? $('.header-top')[0].getBoundingClientRect().right : widget.right;

      this.options.position = {
        my: 'left top',
        at: 'left bottom',
        of: $widget,
        collision: 'none'
      };
      this.menu.element.outerWidth(right - widget.left);
    };
  });
})(window.jQuery);

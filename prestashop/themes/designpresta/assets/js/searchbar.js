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
      var $anchor = mobile.matches ? $('.header-top') : $widget;

      this.options.position = {
        my: 'left top',
        at: 'left bottom',
        of: $anchor,
        collision: 'none'
      };
      this.menu.element.outerWidth($anchor.outerWidth());
    };
  });
})(window.jQuery);

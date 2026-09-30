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
      var anchor = $anchor[0].getBoundingClientRect();
      var separator = mobile.matches ? $('.header-top-mobile-separator')[0] : null;
      var left = separator ? separator.getBoundingClientRect().right : anchor.left;

      this.options.position = {
        my: 'left top',
        at: 'left+' + Math.round(left - anchor.left) + ' bottom',
        of: $anchor,
        collision: 'none'
      };
      this.menu.element.outerWidth(anchor.right - left);
    };
  });
})(window.jQuery);

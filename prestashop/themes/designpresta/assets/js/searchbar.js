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

    instance._resizeMenu = function () {
      this.options.position = {
        my: 'left top',
        at: 'left bottom',
        of: $widget
      };
      this.menu.element.outerWidth($widget.outerWidth());
    };
  });
})(window.jQuery);

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
      this.menu.element.outerWidth($widget.outerWidth());
    };
  });
})(window.jQuery);

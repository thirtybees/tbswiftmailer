<script type="application/javascript">
  $(document).ready(function () {
    if (parseInt($('input[name=PS_MAIL_METHOD]:checked').val(), 10) === 2) {
      $('#configuration_fieldset_smtp').show();
    } else {
      $('#configuration_fieldset_smtp').hide();
    }

    $('input[name=PS_MAIL_METHOD]').on('click', function () {
      if (parseInt($(this).val(), 10) === 2) {
        $('#configuration_fieldset_smtp').slideDown();
      } else {
        $('#configuration_fieldset_smtp').slideUp();
      }
    });
  });
</script>
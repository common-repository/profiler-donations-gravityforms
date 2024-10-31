jQuery(document).ready(function() {
  jQuery(".gf_processcardnumber").change(profilerdonate_cardprocess);
  jQuery(".gf_processcardnumber").keydown(profilerdonate_cardprocess);
});

function profilerdonate_cardprocess() {
  var thisForm = jQuery(this).closest('form');
  var thisValue = jQuery(this).val();
  
  if(jQuery('.gf_pf_cardnum', thisForm).length == 0) {
    jQuery(thisForm).append('<input type="hidden" class="gf_pf_cardnum" name="gf_pf_cardnum" />');
  }
  
  jQuery('.gf_pf_cardnum', thisForm).val(thisValue);
  
}
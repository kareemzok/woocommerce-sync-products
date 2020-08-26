jQuery.noConflict();
jQuery(document).ready(function(){
  jQuery("#btn-sync-button").click(function(){
	jQuery("#btn-sync-button").html("Please wait...");
	jQuery("#sync-loading").addClass("loading-bar"); 
		jQuery("#sync-loading").html("Please wait...");

  });
  
});
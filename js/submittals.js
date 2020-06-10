jQuery(document).ready(function() {
	jQuery('.csspage').hide();
	jQuery('ul ul.sublist').hide();
	jQuery('.csspage:first-of-type').show() //.children('ul.list:first-of-type').children('li').addClass('selected').siblings('ul.sublist').slideDown();
	jQuery('li.tab:first-of-type').addClass('selected');
	jQuery('input[type="tel"]').mask('(000) 000-0000');
	
	jQuery('li.tab').click(function(event) {
		jQuery('li.tab').removeClass('selected');
		jQuery(this).addClass('selected');
		event.preventDefault();
		var tab = jQuery(this).data('target');
		jQuery('.csspage').hide();
		jQuery('ul.sublist').slideUp()
		jQuery('ul.list > li').removeClass('selected');
		jQuery('#'+tab).show() //.children('ul.list:first-of-type').children('li').addClass('selected').siblings('ul.sublist').slideDown();
	});
	jQuery('div.addprojectdetails').click(function() {
		jQuery('li.tab').removeClass('selected');
		jQuery('ul.list > li').removeClass('selected');
		jQuery('.csspage').hide();
		jQuery('#li-additionalinfo').addClass('selected');
		jQuery('#tab-additionalinfo').show();
	});
	
	jQuery('input[type="checkbox"].pdfcheck').bind('change', function(event) {
		event.preventDefault();
		var checked = [];
		jQuery('input[type="checkbox"]').each(function(index, value) {
			if (this.checked) {
				checked.push(jQuery(this).val());
			}
		});
		jQuery('ul.selectlist').html('');
		checked.forEach(function(item) {
			var filename = item.substring(item.lastIndexOf("/") + 1);
			jQuery('ul.selectlist').append('<li class="files"><span class="filename">'+filename+'</span><br /><a href="'+item+'" target="_blank">View PDF</a> <span class="remove" data-file="'+item+'">Remove</span></li>');
		});
	});
	
	jQuery('ul.list > li').click(function(event) {
		jQuery(this).siblings('ul.sublist').slideToggle();
		jQuery(this).toggleClass('selected');
		event.preventDefault();	
		
	});
	
	jQuery('ul.selectlist').on('click', 'span.remove', function() {
		var file = jQuery(this).data('file');
		jQuery("input[type=checkbox][value='"+file+"']").prop("checked",false);
		jQuery(this).parent('li').remove();
	});
	jQuery('.button.submit').click( function() {
		var data = {};
		jQuery('#submittals').serializeArray().map(function(x){data[x.name] = x.value;});
		if (!("docs[]" in data)) {
			alert('You need to select some docments');
			return false;
		}
		
		jQuery("#submittals").submit();
	});
	jQuery("#submittals").validate({
	  submitHandler: function(form) {
	  
		var form = jQuery('#submittals');
		var data = form.serialize();
		
		jQuery(".button.submit").replaceWith( "<h2>Processing...</h2>" );
		
		jQuery.post(
			css_ajax_object.ajax_url, data,
			function(response) {
				var obj = JSON.parse(response);
				window.location.href = window.location.pathname+"?"+jQuery.param({'success':'true','project':obj.project,'email':obj.email})
			}
		);
	  }
	});
	
	jQuery('.remove').click( function(e) {
		var thisli = jQuery(this);
		var form = jQuery(this).data('project');
		
		jQuery.post(
			css_ajax_object.ajax_url, {
			'action': 'css_remove',
			'data': form
			},
			function(response) {
				jQuery(thisli).parent('li').remove();
			}
		);
		
	});

});
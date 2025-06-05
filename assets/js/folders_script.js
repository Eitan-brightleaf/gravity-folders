jQuery(($) => {
	const makeAjaxRequest = function (body) {
		$('html, body').css('cursor', 'wait');
		$.post(ajaxurl, body)
			.done(function (response) {
				if (response.success) {
					window.location.reload();
				} else {
					console.error('Failed:', response.data || 'Unknown error');
				}
			})
			.fail(function (xhr, status, error) {
				console.error('AJAX error:', error);
			})
			.always(function () {
				// Restore default cursor
				$('html, body').css('cursor', 'default');
			});
	};

	$('.dropdown').hover(
		function () {
			$(this).find('.dropdown-menu').stop(true, true).fadeIn(200);
		},
		function () {
			$(this).find('.dropdown-menu').stop(true, true).fadeOut(200);
		}
	);

	$('#rename-folder-form').on('submit', function (e) {
		e.preventDefault();

		const folderID = $(this).find('[name="folder_id"]').val();
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action: 'rename_folder',
			folderID,
			folderName,
			nonce,
		});
	});

	$('.remove-form').on('click', function () {
		const formID = $(this).data('form-id');
		const nonce = $(this).data('nonce');
		makeAjaxRequest({
			action: 'remove_form_from_folder',
			formID,
			nonce,
		});
	});

	$('.copyable').on('click', function () {
		const $el = $(this);
		navigator.clipboard.writeText($el.html())
			.then(() => {
				$el.css('background-color', '#d4edda'); // success green
				setTimeout(() => {
					$el.css('background-color', '');
				}, 1000);
			})
			.catch((err) => {
				console.error('Clipboard copy failed:', err);
			});
	});

});

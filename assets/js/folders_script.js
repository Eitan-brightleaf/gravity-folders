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

	$('#assign-forms-form').on('submit', function (e) {
		e.preventDefault();

		const folderID = $(this).find('[name="folder_id"]').val();
		const formIDs = $(this).find('[name="form_ids[]"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action: 'assign_forms_to_folder',
			formIDs,
			folderID,
			nonce,
		});
	});

	$('.update-form').on('click', function () {
		const action = $(this).data('action');
		const formID = $(this).data('form-id');
		const nonce = $(this).data('nonce');

		const urlParams = new URLSearchParams(window.location.search);
		const folderID = urlParams.get('folder_id'); // could be null


		makeAjaxRequest({
			action,
			formID,
			nonce,
			folderID,
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

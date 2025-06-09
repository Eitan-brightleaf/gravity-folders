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

		const action = 'go_gf_rename_view_folder';
		const folderID = $(this).find('[name="folder_id"]').val();
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			folderID,
			folderName,
			nonce,
		});
	});

	$('#assign-views-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_gf_assign_views_to_folder';
		const folderID = $(this).find('[name="folder_id"]').val();
		const viewIDs = $(this).find('[name="view_ids[]"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			viewIDs,
			folderID,
			nonce,
		});
	});

	$('.update-view').on('click', function () {
		const action = $(this).data('action');
		const viewID = $(this).data('view-id');
		const nonce = $(this).data('nonce');

		const urlParams = new URLSearchParams(window.location.search);
		const folderID = urlParams.get('folder_id'); // could be null

		makeAjaxRequest({
			action,
			viewID,
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

	$('#create-folder-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_gf_create_view_folder';
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			folderName,
			nonce,
		});
	});

	$('.delete-folder-button').on('click', function () {
		const action = 'go_gf_delete_view_folder';
		const folderID = $(this).data('folder-id');
		const nonce = $(this).data('nonce');
		makeAjaxRequest({
			action,
			folderID,
			nonce,
		});
	});
});
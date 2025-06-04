document.addEventListener('DOMContentLoaded', function () {
	function handleFormSubmission(formId, action) {
		const form = document.getElementById(formId);
		if (!form) return;

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			let formData = new FormData(this);
			formData.append('action', action);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(() => location.reload());
		});
	}

	delete_folder = function(folder_id, nonce) {
		const body = `action=delete_folder&folder_id=${encodeURIComponent(folder_id)}&nonce=${encodeURIComponent(nonce)}`;
		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body,
		})
		.then(response => response.json())
		.then(() => location.reload())
		.catch(error => console.error('Error:', error));
	};

	remove_form = function (formID, nonce) {
		const body = `action=remove_form_from_folder&form_id=${encodeURIComponent(formID)}&nonce=${encodeURIComponent(nonce)}`;
		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body,
		})
		.then(response => response.json())
		.then(() => location.reload())
		.catch(error => console.error('Error:', error));
	};

	handleFormSubmission('create-folder-form', 'create_folder');
	handleFormSubmission('assign-form-form', 'assign_form_to_folder');
	handleFormSubmission('rename-folder-form', 'rename_folder');

	document.querySelectorAll('.dropdown').forEach(dropdown => {
		const link = dropdown.querySelector('.link');
		const menu = dropdown.querySelector('.dropdown-menu');

		link.addEventListener('mouseover', () => menu.style.display = 'block');
		menu.addEventListener('mouseover', () => menu.style.display = 'block');
		dropdown.addEventListener('mouseleave', () => menu.style.display = 'none');
	});

	document.querySelectorAll(".copyable").forEach(function (element) {
		element.addEventListener("click", function () {
			navigator.clipboard.writeText(element.innerHTML);
			element.style.backgroundColor = "#d4edda";
			setTimeout(() => {
				element.style.backgroundColor = "";
			}, 1000);
		});
	});
});
jQuery(function ($) {
	'use strict';

	var $tabs = $('[data-adminforge-tabs]');
	var $panels = $('.adminforge-panel');

	function activateTab(hash) {
		if (!hash) {
			hash = '#tab-general';
		}

		$tabs.find('a').removeClass('active');
		$tabs.find('a[href="' + hash + '"]').addClass('active');

		$panels.removeClass('active');
		$(hash).addClass('active');
	}

	if (window.location.hash) {
		activateTab(window.location.hash);
	}

	$tabs.on('click', 'a', function (e) {
		e.preventDefault();
		var hash = $(this).attr('href');
		window.location.hash = hash;
		activateTab(hash);
	});

	$('#adminforge-rescan-menus').on('click', function () {
		var $status = $('#adminforge-scan-status');
		$status.text('Scanning menus...');

		$.get(AdminForgeData.ajaxUrl, {
			action: 'adminforge_rescan_menus',
			nonce: AdminForgeData.nonce
		}).done(function (response) {
			if (response && response.success) {
				$status.text(response.data.message + ' (' + response.data.count + ')');
				window.location.reload();
			} else {
				$status.text('Scan failed.');
			}
		}).fail(function () {
			$status.text('Scan failed.');
		});
	});

	$('.adminforge-color-input').on('input change', function () {
		$(this).siblings('.adminforge-color-swatch').css('background', $(this).val());
	});

	function refreshUserResults(term) {
		var $results = $('#adminforge-user-results');
		$results.html('<p>Loading users...</p>');

		$.get(AdminForgeData.ajaxUrl, {
			action: 'adminforge_search_users',
			nonce: AdminForgeData.nonce,
			term: term || ''
		}).done(function (response) {
			if (!response || !response.success) {
				$results.html('<p>Could not load users.</p>');
				return;
			}

			var rows = response.data.results || [];
			var html = '';

			if (!rows.length) {
				html = '<p>No users found.</p>';
				$results.html(html);
				return;
			}

			rows.forEach(function (row) {
				html += '<div class="adminforge-user-result">';
				html += '<span>' + $('<div/>').text(row.label).html() + '</span>';
				html += '<button type="button" class="button button-small adminforge-user-add" data-user-id="' + row.id + '" data-user-label="' + $('<div/>').text(row.label).html() + '">Add</button>';
				html += '</div>';
			});

			$results.html(html);
		}).fail(function () {
			$results.html('<p>Could not load users.</p>');
		});
	}

	refreshUserResults('');

	function addSelectedUser(user) {
		var $selected = $('#adminforge-user-selected');
		if ($selected.find('[data-user-id="' + user.id + '"]').length) {
			return;
		}

		var html = '';
		html += '<span class="adminforge-chip" data-user-id="' + user.id + '">';
		html += $('<div/>').text(user.label).html();
		html += '<button type="button" class="adminforge-chip-remove" aria-label="Remove user">&times;</button>';
		html += '<input type="hidden" name="adminforge[general][target_users][]" value="' + user.id + '" />';
		html += '</span>';
		$selected.append(html);
	}

	$('#adminforge-user-search').on('input', function () {
		var term = $(this).val();
		refreshUserResults(term);
	});

	$(document).on('click', '.adminforge-user-add', function () {
		addSelectedUser({
			id: $(this).data('user-id'),
			label: $(this).data('user-label')
		});
	});

	$(document).on('click', '.adminforge-chip-remove', function () {
		$(this).closest('.adminforge-chip').remove();
	});

	$('#adminforge-menu-search').on('input', function () {
		var term = $(this).val().toLowerCase();
		$('[data-filter-target="menu"] .adminforge-item').each(function () {
			var text = $(this).text().toLowerCase();
			$(this).toggle(text.indexOf(term) !== -1);
		});
	});
});

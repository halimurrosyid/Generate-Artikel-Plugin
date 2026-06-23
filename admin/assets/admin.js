jQuery(document).ready(function($) {
	// Schedule Options Toggle
	$('#post_status').on('change', function() {
		if ($(this).val() === 'future') {
			$('#schedule_options').show();
			$('#schedule_date').prop('required', true);
		} else {
			$('#schedule_options').hide();
			$('#schedule_date').prop('required', false);
		}
	}).trigger('change');

	// Handle Schedule Mode toggle
	$('#schedule_mode').on('change', function() {
		if ($(this).val() === 'daily') {
			$('#wrap_schedule_daily').show();
			$('#wrap_schedule_interval').hide();
		} else {
			$('#wrap_schedule_daily').hide();
			$('#wrap_schedule_interval').show();
		}
	}).trigger('change');

	// Test API Connection
	$('.aaag-test-conn-btn, #aaag_test_connection').on('click', function() {
		var $btn = $(this);
		var provider = $btn.data('provider') || 'anthropic';
		var $result = $('#aaag_test_result_' + provider);
		if (!$result.length) {
			$result = $('#aaag_test_result');
		}
		
		var originalText = $btn.html();
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 2s linear infinite; vertical-align: middle;"></span> Testing...');
		$result.html('');
		
		$.post(aaagAjax.ajaxurl, {
			action: 'aaag_test_connection',
			nonce: aaagAjax.nonce,
			provider: provider
		}, function(response) {
			$btn.prop('disabled', false).html(originalText);
			if (response.success) {
				Swal.fire('Berhasil!', response.data, 'success');
				$result.html('<span style="color:#46b450; font-weight:bold; margin-left: 10px; vertical-align: middle;">' + response.data + '</span>');
			} else {
				Swal.fire('Gagal!', response.data, 'error');
				$result.html('<span style="color:#dc3232; font-weight:bold; margin-left: 10px; vertical-align: middle;">' + response.data + '</span>');
			}
		}).fail(function() {
			$btn.prop('disabled', false).html(originalText);
			Swal.fire('Error', 'Request failed (Server Error)', 'error');
		});
	});

	// Run Job Now
	$('.aaag-run-job-btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var jobId = $btn.data('id');
		
		Swal.fire({
			title: 'Jalankan Sekarang?',
			text: "Anda akan mengeksekusi pembuatan artikel ini sekarang juga tanpa menunggu Cron Job.",
			icon: 'question',
			showCancelButton: true,
			confirmButtonColor: '#3b82f6',
			cancelButtonColor: '#64748b',
			confirmButtonText: 'Ya, Jalankan!'
		}).then((result) => {
			if (result.isConfirmed) {
				$btn.prop('disabled', true).text('Processing...');
				
				$.post(aaagAjax.ajaxurl, {
					action: 'aaag_run_job',
					nonce: aaagAjax.nonce,
					job_id: jobId
				}, function(response) {
					if (response.success) {
						Swal.fire({
							title: 'Berhasil!',
							text: response.data,
							icon: 'success',
							timer: 2000,
							showConfirmButton: false
						}).then(() => {
							location.reload();
						});
					} else {
						Swal.fire('Error', response.data, 'error');
						$btn.prop('disabled', false).text('Run Now');
					}
				}).fail(function() {
					Swal.fire('Error', 'Request failed (Server Error)', 'error');
					$btn.prop('disabled', false).text('Run Now');
				});
			}
		});
	});

	// Delete Campaign Confirmation (SweetAlert2 override)
	$('.aaag-delete-campaign').on('click', function(e) {
		e.preventDefault(); // Prevent direct navigation
		var href = $(this).attr('href');
		var name = $(this).data('name');
		
		Swal.fire({
			title: 'Hapus Campaign?',
			html: 'Anda akan menghapus permanen Campaign <strong>' + name + '</strong> beserta SELURUH antreannya.<br><br><span style="color:red">Tindakan ini tidak dapat dibatalkan!</span>',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#dc3232',
			cancelButtonColor: '#64748b',
			confirmButtonText: 'Ya, Hapus Permanen!'
		}).then((result) => {
			if (result.isConfirmed) {
				window.location.href = href;
			}
		});
	});

	// Clear Logs Confirmation
	$('#aaag-clear-logs-btn').on('click', function(e) {
		e.preventDefault();
		var form = $(this).closest('form');
		
		Swal.fire({
			title: 'Bersihkan Semua Logs?',
			html: 'Semua catatan eksekusi akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#dc3232',
			cancelButtonColor: '#64748b',
			confirmButtonText: 'Ya, Bersihkan!'
		}).then((result) => {
			if (result.isConfirmed) {
				form.off('submit').submit();
			}
		});
	});

	// Delete Job Confirmation
	$('.aaag-delete-job').on('click', function(e) {
		e.preventDefault();
		var href = $(this).attr('href');
		var id = $(this).data('id');
		
		Swal.fire({
			title: 'Hapus Antrean?',
			html: 'Anda akan menghapus antrean ID <strong>' + id + '</strong>.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#dc3232',
			cancelButtonColor: '#64748b',
			confirmButtonText: 'Ya, Hapus!'
		}).then((result) => {
			if (result.isConfirmed) {
				window.location.href = href;
			}
		});
	});

	// Token Estimation (Fixed for v2.1/v3.0 UI)
	function estimateTokens() {
		var prompt = $('#prompt').val() || '';
		var kb = $('#knowledge_base').val() || '';
		var titleLines = ($('#titles').val() || '').split('\n');
		var maxTitleLength = 0;
		var titleCount = 0;
		
		for (var i = 0; i < titleLines.length; i++) {
			var line = titleLines[i].trim();
			if (line.length > 0) {
				titleCount++;
				if (line.length > maxTitleLength) {
					maxTitleLength = line.length;
				}
			}
		}

		var totalChars = prompt.length + kb.length + maxTitleLength;
		// Rough estimate: 4 chars per token
		var estimatedTokens = Math.ceil(totalChars / 4);

		if (titleCount > 0) {
			$('#token_estimation').html('Estimasi Input: <strong style="font-size:16px; color:#d97706;">~' + estimatedTokens + ' tokens</strong> per artikel (' + titleCount + ' Total Judul)');
		} else {
			$('#token_estimation').text('Estimasi Input: 0 tokens');
		}
	}

	$('#prompt, #knowledge_base, #titles').on('change keyup', estimateTokens);
	// Trigger on load if elements exist
	if ($('#titles').length) {
		estimateTokens();
	}
});

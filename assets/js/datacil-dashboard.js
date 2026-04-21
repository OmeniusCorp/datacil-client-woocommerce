/**
 * Dashboard admin: carga balance, historial y costos al abrir la pagina.
 */
(function ($) {
	'use strict';

	function load(action, onSuccess) {
		return $.post(DatacilAdmin.ajaxUrl, {
			action: action,
			nonce: DatacilAdmin.nonce
		}).done(function (res) {
			if (res && res.success) onSuccess(res.data);
			else onSuccess(null, (res && res.message) || DatacilAdmin.i18n.error);
		}).fail(function () {
			onSuccess(null, DatacilAdmin.i18n.error);
		});
	}

	function renderBalance(data, err) {
		var $el = $('#datacil-balance');
		if (err || !data) { $el.text('—'); return; }
		var balance = data.balance != null ? data.balance : (data.credits != null ? data.credits : 0);
		$el.text(Number(balance).toLocaleString());
	}

	function renderCosts(data, err) {
		var $tbody = $('#datacil-costs-table tbody').empty();
		// La API devuelve {costs: [...], currency: "credits"}; algunas versiones
		// pueden devolver array directo. Normalizamos antes de iterar.
		var list = Array.isArray(data) ? data : (data && Array.isArray(data.costs) ? data.costs : null);
		if (err || !list) {
			$tbody.append('<tr><td colspan="2">' + (err || DatacilAdmin.i18n.empty) + '</td></tr>');
			return;
		}
		if (list.length === 0) {
			$tbody.append('<tr><td colspan="2">' + DatacilAdmin.i18n.empty + '</td></tr>');
			return;
		}
		list.forEach(function (row) {
			$tbody.append(
				'<tr>' +
					'<td>' + escapeHtml(row.service_key || row.serviceKey || '') + '</td>' +
					'<td>' + escapeHtml(String(row.cost)) + '</td>' +
				'</tr>'
			);
		});
	}

	function renderHistory(data, err) {
		var $tbody = $('#datacil-history-table tbody').empty();
		var list = (data && (data.transactions || data.items || data)) || [];
		if (err || !Array.isArray(list)) {
			$tbody.append('<tr><td colspan="4">' + (err || DatacilAdmin.i18n.empty) + '</td></tr>');
			return;
		}
		if (list.length === 0) {
			$tbody.append('<tr><td colspan="4">' + DatacilAdmin.i18n.empty + '</td></tr>');
			return;
		}
		list.forEach(function (tx) {
			var date = tx.createdAt || tx.created_at || '';
			var service = tx.serviceKey || tx.service_key || '';
			var query = tx.queryParam || tx.query_param || '';
			var amount = tx.amount || tx.credits || '';
			$tbody.append(
				'<tr>' +
					'<td>' + escapeHtml(date) + '</td>' +
					'<td>' + escapeHtml(service) + '</td>' +
					'<td>' + escapeHtml(String(query)) + '</td>' +
					'<td>' + escapeHtml(String(amount)) + '</td>' +
				'</tr>'
			);
		});
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	$(function () {
		if (!$('#datacil-balance').length) return; // pagina sin configurar
		load('datacil_credits', renderBalance);
		load('datacil_costs', renderCosts);
		load('datacil_credits_history', renderHistory);
	});
})(jQuery);
